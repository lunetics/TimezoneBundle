<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle;

use Lunetics\TimezoneBundle\Bridge\Console\DebugTimezoneCommand;
use Lunetics\TimezoneBundle\Bridge\Form\TimezoneTypeExtension;
use Lunetics\TimezoneBundle\Bridge\MaxMind\LazyGeoIp2CityReader;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindCityReaderInterface;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindDatabaseCheckCommand;
use Lunetics\TimezoneBundle\Bridge\Messenger\DispatchTimezoneMiddleware;
use Lunetics\TimezoneBundle\Bridge\Messenger\WorkerTimezoneMiddleware;
use Lunetics\TimezoneBundle\Clock\SystemClock;
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProvider;
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Lunetics\TimezoneBundle\Context\TimezoneExecutionContext;
use Lunetics\TimezoneBundle\Context\TimezoneExecutionContextInterface;
use Lunetics\TimezoneBundle\Controller\BrowserTimezoneController;
use Lunetics\TimezoneBundle\DependencyInjection\Compiler\TimezoneCompilerPass;
use Lunetics\TimezoneBundle\EventListener\InvalidPreferenceCleanupListener;
use Lunetics\TimezoneBundle\EventListener\ResolveTimezoneListener;
use Lunetics\TimezoneBundle\Resolution\PersistenceFailureStrategy;
use Lunetics\TimezoneBundle\Resolution\ResolutionFailureStrategy;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolverChain;
use Lunetics\TimezoneBundle\Resolver\HeaderTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\HeaderTrustMode;
use Lunetics\TimezoneBundle\Resolver\CountryTimezoneSourceInterface;
use Lunetics\TimezoneBundle\Resolver\LocaleMappingTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\LocaleTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\MaxMindTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\OidcTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\PhpCountryTimezoneSource;
use Lunetics\TimezoneBundle\Resolver\RequestAttributeTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\CookieTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\PreferenceWriteMarkingStorage;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\SessionTimezoneStorage;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @phpstan-type Toggle 'auto'|bool
 * @phpstan-type Configuration array{
 *   default_timezone: string,
 *   resolution: array{
 *     failure_strategy: 'continue'|'throw',
 *     request_attribute: array{enabled: bool, attribute: string, priority: int},
 *     header: array{enabled: bool, name: string, trust: 'framework'|'allowlist'|'any', trusted_sources: list<string>, priority: int},
 *     user: array{enabled: Toggle, accessor: ?string, priority: int},
 *     oidc: array{enabled: bool, service: ?string, claim: string, priority: int},
 *     maxmind: array{enabled: bool, database: ?string, reader: ?string, priority: int},
 *     locale_mapping: array{enabled: Toggle, mapping: array<string, string>, priority: int},
 *     locale: array{enabled: bool, priority: int}
 *   },
 *   persistence: array{
 *     storage: string,
 *     failure_strategy: 'continue'|'throw',
 *     session: array{key: string},
 *     cookie: array{name: string, max_age: int, path: string, domain: ?string, secure: 'auto'|bool, http_only: bool, same_site: 'lax'|'strict'|'none', secret: ?string, future_skew: int, max_size: int}
 *   },
 *   browser: array{enabled: bool, csrf: array{enabled: bool, token_id: string, header: string}},
 *   integrations: array{twig: Toggle, form: bool, messenger: bool, profiler: Toggle}
 * }
 */
final class LuneticsTimezoneBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();
        $rootNode
            ->children()
                ->scalarNode('default_timezone')->defaultValue('UTC')->cannotBeEmpty()->end()
                ->arrayNode('resolution')->addDefaultsIfNotSet()->children()
                    ->enumNode('failure_strategy')->values(['continue', 'throw'])->defaultValue('continue')->end()
                    ->arrayNode('request_attribute')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('attribute')->defaultValue('_timezone')->cannotBeEmpty()->end()
                        ->integerNode('priority')->defaultValue(1000)->end()
                    ->end()->end()
                    ->arrayNode('header')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('name')->defaultValue('X-Timezone')->cannotBeEmpty()->end()
                        ->enumNode('trust')->values(['framework', 'allowlist', 'any'])->defaultValue('framework')->end()
                        ->arrayNode('trusted_sources')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue([])->end()
                        ->integerNode('priority')->defaultValue(950)->end()
                    ->end()->end()
                    ->arrayNode('user')->addDefaultsIfNotSet()->children()
                        ->enumNode('enabled')->values(['auto', true, false])->defaultValue('auto')->end()
                        ->scalarNode('accessor')->defaultNull()->end()
                        ->integerNode('priority')->defaultValue(900)->end()
                    ->end()->end()
                    ->arrayNode('oidc')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('service')->defaultNull()->end()
                        ->scalarNode('claim')->defaultValue('zoneinfo')->cannotBeEmpty()->end()
                        ->integerNode('priority')->defaultValue(850)->end()
                    ->end()->end()
                    ->arrayNode('maxmind')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('database')->defaultNull()->end()
                        ->scalarNode('reader')->defaultNull()->end()
                        ->integerNode('priority')->defaultValue(400)->end()
                    ->end()->end()
                    ->arrayNode('locale_mapping')->addDefaultsIfNotSet()->children()
                        ->enumNode('enabled')->values(['auto', true, false])->defaultValue('auto')->end()
                        ->arrayNode('mapping')->useAttributeAsKey('locale')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue([])->end()
                        ->integerNode('priority')->defaultValue(200)->end()
                    ->end()->end()
                    ->arrayNode('locale')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('priority')->defaultValue(100)->end()
                    ->end()->end()
                ->end()->end()
                ->arrayNode('persistence')->addDefaultsIfNotSet()->children()
                    ->scalarNode('storage')->defaultValue('session')->cannotBeEmpty()->end()
                    ->enumNode('failure_strategy')->values(['continue', 'throw'])->defaultValue('continue')->end()
                    ->arrayNode('session')->addDefaultsIfNotSet()->children()
                        ->scalarNode('key')->defaultValue('_lunetics_timezone')->cannotBeEmpty()->end()
                    ->end()->end()
                    ->arrayNode('cookie')->addDefaultsIfNotSet()->children()
                        ->scalarNode('name')->defaultValue('_lunetics_timezone')->cannotBeEmpty()->end()
                        ->integerNode('max_age')->min(1)->defaultValue(31536000)->end()
                        ->scalarNode('path')->defaultValue('/')->cannotBeEmpty()->end()
                        ->scalarNode('domain')->defaultNull()->end()
                        ->enumNode('secure')->values(['auto', true, false])->defaultValue('auto')->end()
                        ->booleanNode('http_only')->defaultTrue()->end()
                        ->enumNode('same_site')->values(['lax', 'strict', 'none'])->defaultValue('lax')->end()
                        ->scalarNode('secret')->defaultNull()->end()
                        ->integerNode('future_skew')->min(0)->defaultValue(60)->end()
                        ->integerNode('max_size')->min(1)->max(4096)->defaultValue(4096)->end()
                    ->end()->end()
                ->end()->end()
                ->arrayNode('browser')->addDefaultsIfNotSet()->children()
                    ->booleanNode('enabled')->defaultFalse()->end()
                    ->arrayNode('csrf')->canBeDisabled()->children()
                        ->scalarNode('token_id')->defaultValue('lunetics_timezone.preference')->cannotBeEmpty()->end()
                        ->scalarNode('header')->defaultValue('X-CSRF-Token')->cannotBeEmpty()->end()
                    ->end()->end()
                ->end()->end()
                ->arrayNode('integrations')->addDefaultsIfNotSet()->children()
                    ->enumNode('twig')->values(['auto', true, false])->defaultValue('auto')->end()
                    ->booleanNode('form')->defaultFalse()->end()
                    ->booleanNode('messenger')->defaultFalse()->end()
                    ->enumNode('profiler')->values(['auto', true, false])->defaultValue('auto')->end()
                ->end()->end()
            ->end();
    }

    /** @param Configuration $config */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        self::validateConfig($config);
        $services = $configurator->services()->defaults()->autowire()->autoconfigure();
        $services->set(SystemClock::class);
        $services->alias(TimezoneCompilerPass::CLOCK_SERVICE, SystemClock::class);
        $services->set(TimezoneExecutionContext::class)->tag('kernel.reset', ['method' => 'reset']);
        $services->alias(TimezoneExecutionContextInterface::class, TimezoneExecutionContext::class);
        $services->set('lunetics_timezone.default_timezone', TimezoneId::class)
            ->factory([TimezoneId::class, 'fromString'])->args([$config['default_timezone']]);
        $services->set(CurrentTimezoneProvider::class)->arg('$defaultTimezone', service('lunetics_timezone.default_timezone'));
        $services->alias(CurrentTimezoneProviderInterface::class, CurrentTimezoneProvider::class)->public();

        $storage = $config['persistence']['storage'];
        if ('session' === $storage) {
            $services->set(SessionTimezoneStorage::class)->arg('$key', $config['persistence']['session']['key']);
            $services->alias('lunetics_timezone.storage.configured', SessionTimezoneStorage::class);
        } elseif ('cookie' === $storage) {
            $cookie = $config['persistence']['cookie'];
            $services->set(CookieTimezoneStorage::class)
                ->args([$cookie['secret'], service(TimezoneCompilerPass::CLOCK_SERVICE)])
                ->arg('$name', $cookie['name'])->arg('$maxAge', $cookie['max_age'])->arg('$path', $cookie['path'])
                ->arg('$domain', $cookie['domain'])->arg('$secure', 'auto' === $cookie['secure'] ? null : $cookie['secure'])
                ->arg('$httpOnly', $cookie['http_only'])->arg('$sameSite', $cookie['same_site'])
                ->arg('$futureSkew', $cookie['future_skew'])->arg('$maxEncodedSize', $cookie['max_size']);
            $services->alias('lunetics_timezone.storage.configured', CookieTimezoneStorage::class);
        } else {
            $services->alias('lunetics_timezone.storage.configured', $storage);
        }
        $services->set(PreferenceWriteMarkingStorage::class)
            ->args([service('lunetics_timezone.storage.configured'), service(RequestStack::class)]);
        $services->alias(TimezonePreferenceStorageInterface::class, PreferenceWriteMarkingStorage::class);

        $resolution = $config['resolution'];
        $container->setParameter('lunetics_timezone.resolution.user', $resolution['user']);
        $container->setParameter('lunetics_timezone.default_timezone_value', $config['default_timezone']);
        $container->setParameter('lunetics_timezone.integrations.twig', $config['integrations']['twig']);
        $container->setParameter('lunetics_timezone.integrations.profiler', $config['integrations']['profiler']);
        $container->setParameter('lunetics_timezone.browser.csrf_enabled', $config['browser']['enabled'] && $config['browser']['csrf']['enabled']);
        /** @var list<array{name: string, priority: int, order: int}> $resolverCatalog */
        $resolverCatalog = [];
        $catalogOrder = 0;
        $addToCatalog = static function (string $name, int $priority) use (&$resolverCatalog, &$catalogOrder): void {
            $resolverCatalog[] = ['name' => $name, 'priority' => $priority, 'order' => $catalogOrder++];
        };
        if ($resolution['request_attribute']['enabled']) {
            $services->set(RequestAttributeTimezoneResolver::class)->arg('$attribute', $resolution['request_attribute']['attribute'])
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['request_attribute']['priority'], 'index' => 'request_attribute']);
            $addToCatalog('request_attribute', $resolution['request_attribute']['priority']);
        }
        if ($resolution['header']['enabled']) {
            $services->set(HeaderTimezoneResolver::class)->args([$resolution['header']['name'], HeaderTrustMode::from($resolution['header']['trust']), $resolution['header']['trusted_sources']])
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['header']['priority'], 'index' => 'header']);
            $addToCatalog('header', $resolution['header']['priority']);
        }
        $persistenceStrategy = PersistenceFailureStrategy::from($config['persistence']['failure_strategy']);
        $services->set('lunetics_timezone.resolver.stored_manual', StoredPreferenceTimezoneResolver::class)
            ->args([service(TimezonePreferenceStorageInterface::class), PreferenceSource::MANUAL, $persistenceStrategy])
            ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => 925, 'index' => 'stored_manual']);
        $addToCatalog('stored_manual', 925);

        if ($resolution['oidc']['enabled']) {
            $oidcService = $resolution['oidc']['service'];
            if (!is_string($oidcService) || '' === trim($oidcService)) {
                throw new \LogicException('OIDC timezone resolution requires a non-empty resolution.oidc.service implementing OidcClaimsProviderInterface.');
            }
            $services->set(OidcTimezoneResolver::class)
                ->args([service($oidcService), $resolution['oidc']['claim']])
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['oidc']['priority'], 'index' => 'oidc']);
            $addToCatalog('oidc', $resolution['oidc']['priority']);
        }

        $services->set('lunetics_timezone.resolver.stored_browser', StoredPreferenceTimezoneResolver::class)
            ->args([service(TimezonePreferenceStorageInterface::class), PreferenceSource::BROWSER, $persistenceStrategy])
            ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => 800, 'index' => 'stored_browser']);
        $addToCatalog('stored_browser', 800);

        if ($resolution['maxmind']['enabled']) {
            $reader = $resolution['maxmind']['reader'];
            if (null !== $resolution['maxmind']['database']) {
                if (!class_exists('GeoIp2\\Database\\Reader')) {
                    throw new \LogicException('MaxMind database resolution requires geoip2/geoip2. Install it or configure resolution.maxmind.reader.');
                }
                $services->set(LazyGeoIp2CityReader::class)->arg('$databasePath', $resolution['maxmind']['database']);
                $services->alias(MaxMindCityReaderInterface::class, LazyGeoIp2CityReader::class);
                if (class_exists('Symfony\\Component\\Console\\Command\\Command')) {
                    $services->set(MaxMindDatabaseCheckCommand::class)->arg('$databasePath', $resolution['maxmind']['database']);
                }
            } else {
                $services->alias(MaxMindCityReaderInterface::class, (string) $reader);
            }
            $services->set(MaxMindTimezoneResolver::class)
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['maxmind']['priority'], 'index' => 'maxmind']);
            $addToCatalog('maxmind', $resolution['maxmind']['priority']);
        }
        $mappingEnabled = true === $resolution['locale_mapping']['enabled'] || ('auto' === $resolution['locale_mapping']['enabled'] && [] !== $resolution['locale_mapping']['mapping']);
        if ($mappingEnabled) {
            $services->set(LocaleMappingTimezoneResolver::class)->arg('$mapping', $resolution['locale_mapping']['mapping'])
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['locale_mapping']['priority'], 'index' => 'locale_mapping']);
            $addToCatalog('locale_mapping', $resolution['locale_mapping']['priority']);
        }
        if ($resolution['locale']['enabled']) {
            $services->set(PhpCountryTimezoneSource::class);
            $services->alias(CountryTimezoneSourceInterface::class, PhpCountryTimezoneSource::class);
            $services->set(LocaleTimezoneResolver::class)
                ->tag(TimezoneCompilerPass::RESOLVER_TAG, ['priority' => $resolution['locale']['priority'], 'index' => 'locale']);
            $addToCatalog('locale', $resolution['locale']['priority']);
        }
        usort($resolverCatalog, static fn (array $left, array $right): int => [$right['priority'], $left['order']] <=> [$left['priority'], $right['order']]);
        $resolverCatalog = array_map(static fn (array $resolver): array => ['name' => $resolver['name'], 'priority' => $resolver['priority']], $resolverCatalog);
        $services->set(TimezoneResolverChain::class)->args([
            new TaggedIteratorArgument(TimezoneCompilerPass::RESOLVER_TAG, 'index', null, true),
            service('lunetics_timezone.default_timezone'),
            ResolutionFailureStrategy::from($resolution['failure_strategy']),
            service('logger')->nullOnInvalid(),
        ]);
        $services->set(ResolveTimezoneListener::class);
        $services->set(InvalidPreferenceCleanupListener::class)->arg('$failureStrategy', $persistenceStrategy);

        if (class_exists('Symfony\\Component\\Console\\Command\\Command')) {
            $services->set(DebugTimezoneCommand::class)->args([$config['default_timezone'], $resolverCatalog]);
        }

        if ($config['integrations']['form']) {
            if (!class_exists('Symfony\\Component\\Form\\AbstractTypeExtension')) {
                throw new \LogicException('Form timezone integration requires symfony/form. Install it or set integrations.form=false.');
            }
            $services->set(TimezoneTypeExtension::class)->tag('form.type_extension');
        }

        if ($config['integrations']['messenger']) {
            if (!interface_exists('Symfony\\Component\\Messenger\\Middleware\\MiddlewareInterface')) {
                throw new \LogicException('Messenger timezone integration requires symfony/messenger. Install it or set integrations.messenger=false.');
            }
            $services->set(DispatchTimezoneMiddleware::class);
            $services->set(WorkerTimezoneMiddleware::class);
            $services->alias('lunetics_timezone.messenger.dispatch_middleware', DispatchTimezoneMiddleware::class)->public();
            $services->alias('lunetics_timezone.messenger.worker_middleware', WorkerTimezoneMiddleware::class)->public();
        }

        if ($config['browser']['enabled']) {
            $csrfEnabled = $config['browser']['csrf']['enabled'];
            $services->set(BrowserTimezoneController::class)
                ->public()
                ->tag('controller.service_arguments')
                ->arg('$clock', service(TimezoneCompilerPass::CLOCK_SERVICE))
                ->arg('$csrfTokenManager', $csrfEnabled ? service('security.csrf.token_manager') : null)
                ->arg('$csrfTokenId', $config['browser']['csrf']['token_id'])
                ->arg('$csrfHeader', $config['browser']['csrf']['header']);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TimezoneCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }

    /** @param Configuration $config */
    private static function validateConfig(array $config): void
    {
        TimezoneId::fromString($config['default_timezone']);
        $header = $config['resolution']['header'];
        if ('allowlist' === $header['trust'] && [] === $header['trusted_sources']) {
            throw new \InvalidArgumentException('Header allowlist trust requires at least one trusted_sources entry.');
        }
        $mapping = $config['resolution']['locale_mapping'];
        $normalizedLocales = [];
        foreach ($mapping['mapping'] as $locale => $timezone) {
            if ('' === $locale) {
                throw new \InvalidArgumentException('Locale mappings require non-empty locale keys.');
            }
            $normalizedLocale = str_replace('-', '_', $locale);
            if (isset($normalizedLocales[$normalizedLocale])) {
                throw new \InvalidArgumentException(sprintf('Locale mapping key "%s" collides after normalization.', $locale));
            }
            $normalizedLocales[$normalizedLocale] = true;
            TimezoneId::fromString($timezone);
        }
        if (true === $mapping['enabled'] && [] === $mapping['mapping']) {
            throw new \InvalidArgumentException('Locale mapping enabled=true requires a non-empty mapping.');
        }
        $maxmind = $config['resolution']['maxmind'];
        foreach (['database', 'reader'] as $option) {
            self::validateNullableNonBlankString($maxmind[$option], sprintf('MaxMind %s', $option));
        }
        if ($maxmind['enabled'] && ((null === $maxmind['database']) === (null === $maxmind['reader']))) {
            throw new \InvalidArgumentException('Enabled MaxMind resolution requires exactly one of database or reader.');
        }
        $storage = $config['persistence']['storage'];
        $cookie = $config['persistence']['cookie'];
        Cookie::create($cookie['name'], raw: true);
        if ('cookie' === $storage) {
            if (!is_string($cookie['secret']) || '' === $cookie['secret']) {
                throw new \InvalidArgumentException('Cookie storage requires a non-empty secret.');
            }
            if ('none' === $cookie['same_site'] && true !== $cookie['secure']) {
                throw new \InvalidArgumentException('SameSite=None requires secure=true.');
            }
            if (str_starts_with($cookie['name'], '__Host-') && (true !== $cookie['secure'] || '/' !== $cookie['path'] || null !== $cookie['domain'])) {
                throw new \InvalidArgumentException('__Host- cookies require secure=true, path=/, and no domain.');
            }
            if (str_starts_with($cookie['name'], '__Secure-') && true !== $cookie['secure']) {
                throw new \InvalidArgumentException('__Secure- cookies require secure=true.');
            }
        }
    }

    private static function validateNullableNonBlankString(mixed $value, string $description): void
    {
        if (null !== $value && (!is_string($value) || '' === trim($value))) {
            throw new \InvalidArgumentException(sprintf('%s must be null or a non-empty string.', $description));
        }
    }
}
