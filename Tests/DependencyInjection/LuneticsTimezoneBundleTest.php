<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\DependencyInjection;

use Lunetics\TimezoneBundle\Bridge\Console\DebugTimezoneCommand;
use Lunetics\TimezoneBundle\Bridge\Form\TimezoneTypeExtension;
use Lunetics\TimezoneBundle\Bridge\MaxMind\CallableMaxMindCityReader;
use Lunetics\TimezoneBundle\Bridge\MaxMind\LazyGeoIp2CityReader;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindCityReaderInterface;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindDatabaseCheckCommand;
use Lunetics\TimezoneBundle\Bridge\Messenger\DispatchTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\Messenger\WorkerTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneScope;
use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneSubscriber;
use Lunetics\TimezoneBundle\Bridge\WebProfiler\TimezoneDataCollector;
use Lunetics\TimezoneBundle\Clock\SystemClock;
use Lunetics\TimezoneBundle\Contract\Oidc\OidcClaimsProviderInterface;
use Lunetics\TimezoneBundle\Contract\User\UserTimezoneAccessorInterface;
use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\DependencyInjection\Compiler\TimezoneCompilerPass;
use Lunetics\TimezoneBundle\LuneticsTimezoneBundle;
use Lunetics\TimezoneBundle\Resolver\MaxMindTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\OidcTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface;
use Lunetics\TimezoneBundle\Resolver\UserTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class LuneticsTimezoneBundleTest extends TestCase
{
    public function testBundlePathIsPackageRoot(): void
    {
        self::assertSame(dirname(__DIR__, 2), (new LuneticsTimezoneBundle())->getPath());
    }

    public function testMinimalConfigurationCompilesAndPreservesBuiltInOrder(): void
    {
        $container = $this->container();
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();

        $tagged = $container->findTaggedServiceIds(TimezoneCompilerPass::RESOLVER_TAG);
        $priorities = [];
        foreach ($tagged as $tags) {
            self::assertIsArray($tags[0]);
            self::assertIsInt($tags[0]['priority']);
            $priorities[] = $tags[0]['priority'];
        }
        rsort($priorities);
        self::assertSame([1000, 925, 800, 100], $priorities);
    }

    public function testEmptyLocaleMappingKeyFailsDuringContainerBuild(): void
    {
        $container = $this->container(['resolution' => ['locale_mapping' => ['mapping' => ['' => 'UTC']]]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty locale keys');
        $container->compile();
    }

    public function testNormalizedLocaleMappingCollisionFailsDuringContainerBuild(): void
    {
        $container = $this->container(['resolution' => ['locale_mapping' => ['mapping' => ['de-DE' => 'Europe/Berlin', 'de_DE' => 'UTC']]]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('collides after normalization');
        $container->compile();
    }

    public function testDuplicateExplicitResolverIndicesFailCompilation(): void
    {
        $container = $this->container();
        $container->setDefinition('duplicate', new Definition(NullResolver::class))
            ->addTag(TimezoneCompilerPass::RESOLVER_TAG, ['index' => 'locale', 'priority' => 100]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tag index "locale" is registered more than once');
        $container->compile();
    }

    public function testResolverTagWithoutIndexFailsCompilation(): void
    {
        $container = $this->container();
        $container->setDefinition('missing_index', new Definition(NullResolver::class))
            ->addTag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => 100]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone resolver "missing_index" must declare a non-empty string "index"');
        $container->compile();
    }

    public function testCustomResolverWithoutPriorityAppearsInDebugCatalogAtZero(): void
    {
        $container = $this->container();
        $container->setDefinition('custom_without_priority', new Definition(NullResolver::class))
            ->addTag(TimezoneCompilerPass::RESOLVER_TAG, ['index' => 'custom']);
        $container->compile();

        $catalog = $container->getDefinition(DebugTimezoneCommand::class)->getArgument(1);
        self::assertIsArray($catalog);
        self::assertContains(['name' => 'custom', 'priority' => 0], $catalog);
    }

    public function testResolverTagWithNonIntegerPriorityFailsCompilation(): void
    {
        $container = $this->container();
        $container->setDefinition('invalid_priority', new Definition(NullResolver::class))
            ->addTag(TimezoneCompilerPass::RESOLVER_TAG, ['index' => 'custom', 'priority' => '100']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timezone resolver "invalid_priority" must declare an integer "priority"');
        $container->compile();
    }

    public function testCustomStorageMustImplementContract(): void
    {
        $container = $this->container(['persistence' => ['storage' => 'application.storage']]);
        $container->setDefinition('application.storage', new Definition(\stdClass::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(TimezonePreferenceStorageInterface::class);
        $container->compile();
    }

    public function testCircularCustomStorageAliasFailsWithDeterministicMessage(): void
    {
        $container = $this->container(['persistence' => ['storage' => 'application.storage.a']]);
        $container->setAlias('application.storage.a', 'application.storage.b');
        $container->setAlias('application.storage.b', 'application.storage.a');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Alias cycle detected while resolving configured timezone preference storage service "%s": application.storage.a -> application.storage.b -> application.storage.a.',
            TimezonePreferenceStorageInterface::class,
        ));
        $container->compile();
    }

    public function testInternalClockFallsBackWithoutDefiningGlobalClockAlias(): void
    {
        $container = $this->container();
        $container->compile();

        self::assertFalse($container->hasAlias(ClockInterface::class));
        self::assertSame(SystemClock::class, (string) $container->getAlias(TimezoneCompilerPass::CLOCK_SERVICE));
    }

    public function testInternalClockPrefersApplicationClockWithoutChangingGlobalAlias(): void
    {
        $container = $this->container([], static function (ContainerBuilder $container): void {
            $container->setDefinition('application.clock', new Definition(FixedApplicationClock::class));
            $container->setAlias(ClockInterface::class, 'application.clock');
        });
        $container->compile();

        self::assertSame('application.clock', (string) $container->getAlias(ClockInterface::class));
        self::assertSame('application.clock', (string) $container->getAlias(TimezoneCompilerPass::CLOCK_SERVICE));
    }

    public function testUnknownIntegrationOptionIsRejected(): void
    {
        $container = $this->container(['integrations' => ['mesenger' => true]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "mesenger" under "lunetics_timezone.integrations"');
        $container->compile();
    }

    public function testWhitespaceMaxMindDatabaseIsRejected(): void
    {
        $container = $this->container(['resolution' => ['maxmind' => ['database' => " \t "]]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MaxMind database must be null or a non-empty string.');
        $container->compile();
    }

    public function testWhitespaceMaxMindReaderIsRejected(): void
    {
        $container = $this->container(['resolution' => ['maxmind' => ['reader' => " \n "]]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MaxMind reader must be null or a non-empty string.');
        $container->compile();
    }

    public function testInvalidCookieNameIsRejectedByConfiguration(): void
    {
        $container = $this->container(['persistence' => ['cookie' => ['name' => 'invalid cookie']]]);

        $this->expectException(\InvalidArgumentException::class);
        $container->compile();
    }

    public function testBrowserCsrfFailsClearlyWhenOptionalPackageIsMissing(): void
    {
        $container = $this->container(['browser' => ['enabled' => true]]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('security.csrf.token_manager');
        $container->compile();
    }

    public function testBrowserCsrfWiresOnlyWhenTokenManagerServiceExists(): void
    {
        $container = $this->container(['browser' => ['enabled' => true]], static function (ContainerBuilder $container): void {
            self::registerStubExtension($container, 'csrf_stub', static fn (ContainerBuilder $container) => $container->setDefinition('security.csrf.token_manager', new Definition(\stdClass::class)));
        });
        $container->compile();

        self::assertTrue($container->hasDefinition(BrowserTimezoneController::class));
        $definition = $container->getDefinition(BrowserTimezoneController::class);
        self::assertTrue($definition->isPublic());
        self::assertTrue($definition->hasTag('controller.service_arguments'));
    }

    public function testUserAutoRequiresActualTokenStorageServiceAndInjectsConfiguredAccessor(): void
    {
        $withoutSecurity = $this->container();
        $withoutSecurity->compile();
        self::assertFalse($withoutSecurity->hasDefinition(UserTimezoneResolver::class));

        $container = $this->container([
            'resolution' => ['user' => ['enabled' => 'auto', 'accessor' => 'application.user_timezone', 'priority' => 901]],
        ], static function (ContainerBuilder $container): void {
            self::registerStubExtension($container, 'security_stub', static function (ContainerBuilder $container): void {
                $container->setDefinition('application.token_storage', new Definition(\stdClass::class));
                $container->setAlias('security.token_storage', 'application.token_storage');
                $container->setDefinition('application.user_timezone', new Definition(NullUserTimezoneAccessor::class));
            });
        });
        $container->compile();

        $definition = $container->getDefinition(UserTimezoneResolver::class);
        self::assertInstanceOf(Reference::class, $definition->getArgument(0));
        self::assertSame('application.token_storage', (string) $definition->getArgument(0));
        self::assertInstanceOf(Reference::class, $definition->getArgument(1));
        self::assertSame('application.user_timezone', (string) $definition->getArgument(1));
        $tags = $definition->getTag(TimezoneCompilerPass::RESOLVER_TAG);
        self::assertIsArray($tags[0]);
        self::assertSame(901, $tags[0]['priority']);
    }

    public function testDiscoverableUserAccessorMustImplementContractThroughAlias(): void
    {
        $container = $this->container([
            'resolution' => ['user' => ['enabled' => 'auto', 'accessor' => 'application.user_timezone']],
        ], static function (ContainerBuilder $container): void {
            self::registerStubExtension($container, 'security_stub', static function (ContainerBuilder $container): void {
                $container->setDefinition('application.token_storage', new Definition(\stdClass::class));
                $container->setAlias('security.token_storage', 'application.token_storage');
                $container->setDefinition('application.invalid_accessor', new Definition(\stdClass::class));
                $container->setAlias('application.user_timezone', 'application.invalid_accessor');
            });
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UserTimezoneAccessorInterface::class);
        $container->compile();
    }

    public function testExplicitUserIntegrationRequiresTokenStorageService(): void
    {
        $container = $this->container(['resolution' => ['user' => ['enabled' => true]]]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('security.token_storage');
        $container->compile();
    }

    public function testOidcRequiresServiceAndWiresClaimAndPriority(): void
    {
        $invalid = $this->container(['resolution' => ['oidc' => ['enabled' => true]]]);
        try {
            $invalid->compile();
            self::fail('Expected missing OIDC service to fail.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('resolution.oidc.service', $exception->getMessage());
        }

        $container = $this->container([
            'resolution' => ['oidc' => ['enabled' => true, 'service' => 'application.oidc', 'claim' => 'tz', 'priority' => 851]],
        ], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'oidc_stub', static fn (ContainerBuilder $container) => $container->setDefinition('application.oidc', new Definition(NullOidcClaimsProvider::class))));
        $container->compile();

        $definition = $container->getDefinition(OidcTimezoneResolver::class);
        self::assertInstanceOf(Reference::class, $definition->getArgument(0));
        self::assertSame('application.oidc', (string) $definition->getArgument(0));
        self::assertSame('tz', $definition->getArgument(1));
        $tags = $definition->getTag(TimezoneCompilerPass::RESOLVER_TAG);
        self::assertIsArray($tags[0]);
        self::assertSame(851, $tags[0]['priority']);
    }

    public function testDiscoverableOidcServiceMustImplementContract(): void
    {
        $container = $this->container([
            'resolution' => ['oidc' => ['enabled' => true, 'service' => 'application.oidc']],
        ], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'oidc_stub', static fn (ContainerBuilder $container) => $container->setDefinition('application.oidc', new Definition(\stdClass::class))));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(OidcClaimsProviderInterface::class);
        $container->compile();
    }

    public function testMaxMindReaderModeAliasesContractAndDatabaseModeIsLazy(): void
    {
        $reader = $this->container([
            'resolution' => ['maxmind' => ['enabled' => true, 'reader' => 'application.maxmind', 'priority' => 401]],
        ], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'maxmind_stub', static fn (ContainerBuilder $container) => $container->setDefinition('application.maxmind', new Definition(NullMaxMindCityReader::class))));
        $reader->compile();
        self::assertSame('application.maxmind', (string) $reader->getAlias(MaxMindCityReaderInterface::class));
        $tags = $reader->getDefinition(MaxMindTimezoneResolver::class)->getTag(TimezoneCompilerPass::RESOLVER_TAG);
        self::assertIsArray($tags[0]);
        self::assertSame(401, $tags[0]['priority']);

        $database = $this->container(['resolution' => ['maxmind' => ['enabled' => true, 'database' => '/does/not/need/to/exist.mmdb']]]);
        $database->compile();
        self::assertSame(LazyGeoIp2CityReader::class, (string) $database->getAlias(MaxMindCityReaderInterface::class));
        self::assertTrue($database->hasDefinition(MaxMindDatabaseCheckCommand::class));
    }

    public function testDiscoverableMaxMindReaderMustImplementContract(): void
    {
        $container = $this->container([
            'resolution' => ['maxmind' => ['enabled' => true, 'reader' => 'application.maxmind']],
        ], static function (ContainerBuilder $container): void {
            self::registerStubExtension($container, 'maxmind_stub', static function (ContainerBuilder $container): void {
                $container->setDefinition('application.invalid_maxmind', new Definition(\stdClass::class));
                $container->setAlias('application.maxmind', 'application.invalid_maxmind');
            });
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(MaxMindCityReaderInterface::class);
        $container->compile();
    }

    public function testCallableMaxMindReaderWrapperRemainsSupported(): void
    {
        $container = $this->container([
            'resolution' => ['maxmind' => ['enabled' => true, 'reader' => 'application.maxmind']],
        ], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'maxmind_stub', static fn (ContainerBuilder $container) => $container->setDefinition('application.maxmind', new Definition(CallableMaxMindCityReader::class, ['trim']))));

        $container->compile();

        self::assertSame('application.maxmind', (string) $container->getAlias(MaxMindCityReaderInterface::class));
    }

    public function testMaxMindEnabledRequiresExactlyOneSource(): void
    {
        $container = $this->container(['resolution' => ['maxmind' => ['enabled' => true]]]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactly one');
        $container->compile();
    }

    public function testTwigAutoUsesRegisteredExtensionAndCoreExtensionFactory(): void
    {
        $withoutTwig = $this->container();
        $withoutTwig->compile();
        self::assertFalse($withoutTwig->hasDefinition(TwigTimezoneScope::class));

        $container = $this->container([], static function (ContainerBuilder $container): void {
            self::registerStubExtension($container, 'twig', static fn (ContainerBuilder $container) => $container->setDefinition('twig', new Definition('Twig\\Environment')));
        });
        $container->compile();

        $core = $container->getDefinition('lunetics_timezone.twig.core_extension');
        $factory = $core->getFactory();
        self::assertIsArray($factory);
        self::assertSame('getExtension', $factory[1]);
        self::assertSame('Twig\\Extension\\CoreExtension', $core->getArgument(0));
        self::assertTrue($container->getDefinition(TwigTimezoneScope::class)->hasTag('kernel.reset'));
        self::assertTrue($container->hasDefinition(TwigTimezoneSubscriber::class));
    }

    public function testExplicitTwigRequiresExtensionAndService(): void
    {
        $container = $this->container(['integrations' => ['twig' => true]]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Twig container extension');
        $container->compile();
    }

    public function testFormAndMessengerDefinitionsAreOptIn(): void
    {
        $container = $this->container(['integrations' => ['form' => true, 'messenger' => true]]);
        $container->compile();

        self::assertTrue($container->getDefinition(TimezoneTypeExtension::class)->hasTag('form.type_extension'));
        self::assertSame(DispatchTimezoneMiddleware::class, (string) $container->getAlias('lunetics_timezone.messenger.dispatch_middleware'));
        self::assertTrue($container->getAlias('lunetics_timezone.messenger.dispatch_middleware')->isPublic());
        self::assertSame(WorkerTimezoneMiddleware::class, (string) $container->getAlias('lunetics_timezone.messenger.worker_middleware'));
        self::assertFalse($container->hasDefinition('messenger.bus.default'));
    }

    public function testProfilerAutoRequiresExtensionAndDebugAndUsesResolverCatalog(): void
    {
        $container = $this->container([
            'resolution' => ['header' => ['enabled' => true, 'priority' => 925]],
        ], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'web_profiler'));
        $container->compile();

        $definition = $container->getDefinition(TimezoneDataCollector::class);
        self::assertSame([
            ['name' => 'request_attribute', 'priority' => 1000],
            ['name' => 'header', 'priority' => 925],
            ['name' => 'stored_manual', 'priority' => 925],
            ['name' => 'stored_browser', 'priority' => 800],
            ['name' => 'locale', 'priority' => 100],
        ], $definition->getArgument(1));
        $tags = $definition->getTag('data_collector');
        self::assertIsArray($tags[0]);
        self::assertSame('@LuneticsTimezone/Collector/timezone.html.twig', $tags[0]['template']);
        self::assertSame('lunetics_timezone', $tags[0]['id']);

        $notDebug = $this->container([], static function (ContainerBuilder $container): void {
            $container->setParameter('kernel.debug', false);
            self::registerStubExtension($container, 'web_profiler');
        });
        $notDebug->compile();
        self::assertFalse($notDebug->hasDefinition(TimezoneDataCollector::class));
    }

    public function testExplicitProfilerRequiresWebProfilerExtension(): void
    {
        $container = $this->container(['integrations' => ['profiler' => true]]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('web-profiler-bundle');
        $container->compile();
    }

    public function testDebugCommandIsRegisteredWithDeterministicCatalog(): void
    {
        $container = $this->container(['resolution' => ['oidc' => ['enabled' => true, 'service' => 'application.oidc', 'priority' => 925]]], static fn (ContainerBuilder $container) => self::registerStubExtension($container, 'oidc_stub', static fn (ContainerBuilder $container) => $container->setDefinition('application.oidc', new Definition(NullOidcClaimsProvider::class))));
        $container->compile();

        self::assertSame([
            ['name' => 'request_attribute', 'priority' => 1000],
            ['name' => 'stored_manual', 'priority' => 925],
            ['name' => 'oidc', 'priority' => 925],
            ['name' => 'stored_browser', 'priority' => 800],
            ['name' => 'locale', 'priority' => 100],
        ], $container->getDefinition(DebugTimezoneCommand::class)->getArgument(1));
    }

    /** @param array<string, mixed> $config */
    private function container(array $config = [], ?callable $configure = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 2));
        $container->setDefinition(RequestStack::class, new Definition(RequestStack::class));
        $container->setDefinition('event_dispatcher', new Definition(EventDispatcher::class));
        $container->setAlias(EventDispatcherInterface::class, 'event_dispatcher');
        if (null !== $configure) {
            $configure($container);
        }
        $bundle = new LuneticsTimezoneBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);
        $bundle->build($container);
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        return $container;
    }

    private static function registerStubExtension(ContainerBuilder $container, string $alias, ?callable $load = null): void
    {
        $container->registerExtension(new StubExtension($alias, $load));
        $container->loadFromExtension($alias);
    }
}

final class StubExtension extends Extension
{
    private readonly ?\Closure $loader;

    public function __construct(private readonly string $stubAlias, ?callable $loader = null)
    {
        $this->loader = null === $loader ? null : $loader(...);
    }

    public function getAlias(): string
    {
        return $this->stubAlias;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        ($this->loader ?? static function (): void {})($container);
    }
}

final class NullOidcClaimsProvider implements OidcClaimsProviderInterface
{
    public function claimsForRequest(Request $request): array
    {
        return [];
    }
}

final class NullUserTimezoneAccessor implements UserTimezoneAccessorInterface
{
    public function getTimezoneForUser(object $user): null
    {
        return null;
    }
}

final class NullMaxMindCityReader implements MaxMindCityReaderInterface
{
    public function timezoneForIp(string $ipAddress): null
    {
        return null;
    }
}

final class NullResolver implements TimezoneResolverInterface
{
    public function resolve(Request $request): null
    {
        return null;
    }
}

final class FixedApplicationClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-01-01T00:00:00Z');
    }
}
