<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\DependencyInjection\Compiler;

use Lunetics\TimezoneBundle\Bridge\Console\DebugTimezoneCommand;
use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneScope;
use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneSubscriber;
use Lunetics\TimezoneBundle\Bridge\WebProfiler\TimezoneDataCollector;
use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindCityReaderInterface;
use Lunetics\TimezoneBundle\Clock\SystemClock;
use Lunetics\TimezoneBundle\Contract\Oidc\OidcClaimsProviderInterface;
use Lunetics\TimezoneBundle\Contract\User\UserTimezoneAccessorInterface;
use Lunetics\TimezoneBundle\Resolver\OidcTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\UserTimezoneResolver;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

final class TimezoneCompilerPass implements CompilerPassInterface
{
    public const CLOCK_SERVICE = 'lunetics_timezone.clock';
    public const RESOLVER_TAG = 'lunetics_timezone.resolver';

    public function process(ContainerBuilder $container): void
    {
        $container->setAlias(self::CLOCK_SERVICE, $container->has(ClockInterface::class) ? ClockInterface::class : SystemClock::class);
        $this->configureOptionalIntegrations($container);

        $indices = [];
        foreach ($container->findTaggedServiceIds(self::RESOLVER_TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                if (!is_array($attributes)) {
                    throw new InvalidArgumentException(sprintf('Timezone resolver "%s" has invalid tag metadata.', $id));
                }
                $index = $attributes['index'] ?? null;
                if (!is_string($index) || '' === $index) {
                    throw new InvalidArgumentException(sprintf('Timezone resolver "%s" must declare a non-empty string "index" on every "%s" tag.', $id, self::RESOLVER_TAG));
                }
                if (array_key_exists('priority', $attributes) && !is_int($attributes['priority'])) {
                    throw new InvalidArgumentException(sprintf('Timezone resolver "%s" must declare an integer "priority" when it is present on the "%s" tag.', $id, self::RESOLVER_TAG));
                }
                if (isset($indices[$index])) {
                    throw new InvalidArgumentException(sprintf('Timezone resolver tag index "%s" is registered more than once by "%s" and "%s".', $index, $indices[$index], $id));
                }
                $indices[$index] = $id;
            }
        }

        if (!$container->hasAlias('lunetics_timezone.storage.configured')) {
            return;
        }
        $id = $this->resolveServiceId($container, 'lunetics_timezone.storage.configured', 'timezone preference storage');
        if (!$container->hasDefinition($id)) {
            return;
        }
        $class = $container->getDefinition($id)->getClass();
        if (null !== $class && !is_a($class, TimezonePreferenceStorageInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('Configured timezone preference storage service "%s" must implement %s.', $id, TimezonePreferenceStorageInterface::class));
        }
    }

    private function configureOptionalIntegrations(ContainerBuilder $container): void
    {
        /** @var array{enabled: 'auto'|bool, accessor: ?string, priority: int} $user */
        $user = $container->getParameter('lunetics_timezone.resolution.user');
        if (null !== $user['accessor']) {
            $this->validateServiceContract($container, $user['accessor'], UserTimezoneAccessorInterface::class, 'user timezone accessor');
        }
        if ($container->hasDefinition(OidcTimezoneResolver::class)) {
            $oidcService = $container->getDefinition(OidcTimezoneResolver::class)->getArgument(0);
            if ($oidcService instanceof Reference) {
                $this->validateServiceContract($container, (string) $oidcService, OidcClaimsProviderInterface::class, 'OIDC claims provider');
            }
        }
        if ($container->hasAlias(MaxMindCityReaderInterface::class)) {
            $this->validateServiceContract($container, MaxMindCityReaderInterface::class, MaxMindCityReaderInterface::class, 'MaxMind city reader');
        }
        $tokenStorageAvailable = $container->has('security.token_storage');
        if (true === $user['enabled'] && !interface_exists('Symfony\\Component\\Security\\Core\\Authentication\\Token\\Storage\\TokenStorageInterface')) {
            throw new \LogicException('User timezone resolution requires symfony/security-core. Install it or set resolution.user.enabled=false.');
        }
        if (true === $user['enabled'] && !$tokenStorageAvailable) {
            throw new \LogicException('User timezone resolution requires the "security.token_storage" service or alias. Enable Symfony Security or set resolution.user.enabled=false.');
        }
        if (true === $user['enabled'] || ('auto' === $user['enabled'] && $tokenStorageAvailable)) {
            $container->setDefinition(UserTimezoneResolver::class, (new Definition(UserTimezoneResolver::class, [
                new Reference('security.token_storage'),
                null === $user['accessor'] ? null : new Reference($user['accessor']),
            ]))->addTag(self::RESOLVER_TAG, ['priority' => $user['priority'], 'index' => 'user']));
        }

        $twig = $container->getParameter('lunetics_timezone.integrations.twig');
        $twigExtensionAvailable = $container->hasExtension('twig');
        if (true === $twig && (!class_exists('Twig\\Environment') || !$twigExtensionAvailable || !$container->has('twig'))) {
            throw new \LogicException('Twig timezone integration requires twig/twig, symfony/twig-bundle, the Twig container extension, and the "twig" service.');
        }
        if (true === $twig || ('auto' === $twig && $twigExtensionAvailable)) {
            $container->setDefinition('lunetics_timezone.twig.core_extension', (new Definition('Twig\\Extension\\CoreExtension'))
                ->setFactory([new Reference('twig'), 'getExtension'])->setArguments(['Twig\\Extension\\CoreExtension']));
            $container->setDefinition(TwigTimezoneScope::class, (new Definition(TwigTimezoneScope::class, [new Reference('lunetics_timezone.twig.core_extension')]))
                ->addTag('kernel.reset', ['method' => 'reset']));
            $container->setDefinition(TwigTimezoneSubscriber::class, (new Definition(TwigTimezoneSubscriber::class, [new Reference(TwigTimezoneScope::class)]))
                ->addTag('kernel.event_subscriber'));
        }

        $profiler = $container->getParameter('lunetics_timezone.integrations.profiler');
        $profilerExtensionAvailable = $container->hasExtension('web_profiler');
        if (true === $profiler && (!class_exists('Symfony\\Component\\HttpKernel\\DataCollector\\DataCollector') || !$profilerExtensionAvailable)) {
            throw new \LogicException('Profiler timezone integration requires symfony/web-profiler-bundle and its container extension.');
        }
        $profilerEnabled = true === $profiler || ('auto' === $profiler && $profilerExtensionAvailable && true === $container->getParameter('kernel.debug'));

        if (true === $container->getParameter('lunetics_timezone.browser.csrf_enabled')
            && (!interface_exists('Symfony\\Component\\Security\\Csrf\\CsrfTokenManagerInterface') || !$container->has('security.csrf.token_manager'))) {
            throw new \LogicException('Browser timezone CSRF protection requires symfony/security-csrf and the "security.csrf.token_manager" service. Install and enable it or explicitly disable browser.csrf.');
        }

        /** @var list<array{name: string, priority: int, order: int}> $catalog */
        $catalog = [];
        $order = 0;
        foreach ($container->findTaggedServiceIds(self::RESOLVER_TAG) as $id => $tags) {
            foreach ($tags as $attributes) {
                if (!is_array($attributes)) {
                    continue;
                }
                if (isset($attributes['index']) && is_string($attributes['index'])) {
                    $priority = $attributes['priority'] ?? 0;
                    if (!is_int($priority)) {
                        throw new InvalidArgumentException(sprintf('Timezone resolver "%s" must declare an integer "priority" when it is present on the "%s" tag.', $id, self::RESOLVER_TAG));
                    }
                    $catalog[] = ['name' => $attributes['index'], 'priority' => $priority, 'order' => $order++];
                }
            }
        }
        usort($catalog, static fn (array $left, array $right): int => [$right['priority'], $left['order']] <=> [$left['priority'], $right['order']]);
        $resolverCatalog = array_map(static fn (array $resolver): array => ['name' => $resolver['name'], 'priority' => $resolver['priority']], $catalog);
        if ($container->hasDefinition(DebugTimezoneCommand::class)) {
            $container->getDefinition(DebugTimezoneCommand::class)->replaceArgument(1, $resolverCatalog);
        }
        if ($profilerEnabled) {
            $configuredDefault = $container->getParameter('lunetics_timezone.default_timezone_value');
            $container->setDefinition(TimezoneDataCollector::class, (new Definition(TimezoneDataCollector::class, [$configuredDefault, $resolverCatalog]))
                ->addTag('data_collector', ['template' => '@LuneticsTimezone/Collector/timezone.html.twig', 'id' => 'lunetics_timezone']));
        }
    }

    private function validateServiceContract(ContainerBuilder $container, string $serviceId, string $contract, string $description): void
    {
        $resolvedId = $this->resolveServiceId($container, $serviceId, $description);
        if (!$container->hasDefinition($resolvedId)) {
            return;
        }

        $class = $container->getDefinition($resolvedId)->getClass();
        if (null !== $class && !is_a($class, $contract, true)) {
            throw new InvalidArgumentException(sprintf('Configured %s service "%s" (resolved to "%s") must implement %s.', $description, $serviceId, $resolvedId, $contract));
        }
    }

    private function resolveServiceId(ContainerBuilder $container, string $serviceId, string $description): string
    {
        $resolvedId = $serviceId;
        $path = [];
        $seen = [];
        while ($container->hasAlias($resolvedId)) {
            if (isset($seen[$resolvedId])) {
                $cycle = array_slice($path, $seen[$resolvedId]);
                $cycle[] = $resolvedId;
                throw new InvalidArgumentException(sprintf('Alias cycle detected while resolving configured %s service "%s": %s.', $description, $serviceId, implode(' -> ', $cycle)));
            }
            $seen[$resolvedId] = count($path);
            $path[] = $resolvedId;
            $resolvedId = (string) $container->getAlias($resolvedId);
        }

        return $resolvedId;
    }
}
