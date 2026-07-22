# LuneticsTimezoneBundle 2.x

LuneticsTimezoneBundle resolves an IANA timezone for each Symfony request and exposes it through a stable application context. It can combine explicit request data, trusted headers, persisted preferences, authenticated users, OIDC claims, MaxMind City data, and locale fallbacks.

Symfony 8 supplies timezone lists, validation primitives, and form options, but applications still need policy for choosing a user's timezone, retaining it, and carrying it through Twig and asynchronous work. This bundle provides that orchestration without changing PHP's process-wide default timezone.

## Requirements

- PHP `^8.2`
- Symfony `^6.4 || ^7.4 || ^8.0`

Symfony 8.1 is the primary current target (July 2026); the `^8.0` constraint intentionally permits compatible later Symfony 8 minors.

## Install and register

```bash
composer require lunetics/timezone-bundle
```

Register the bundle when Symfony Flex has not done so:

```php
// config/bundles.php
return [
    // ...
    Lunetics\TimezoneBundle\LuneticsTimezoneBundle::class => ['all' => true],
];
```

Minimal configuration:

```yaml
# config/packages/lunetics_timezone.yaml
lunetics_timezone:
    default_timezone: UTC
```

Inject `Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface`:

```php
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;

final class LocalClock
{
    public function __construct(private CurrentTimezoneProviderInterface $timezones) {}

    public function timezone(): \DateTimeZone
    {
        return $this->timezones->getDateTimeZone();
    }
}
```

## Documentation

- [Installation and configuration](Resources/doc/installation.md)
- [Resolvers and precedence](Resources/doc/resolvers.md)
- [Scope and non-goals](Resources/doc/scope.md)
- [V2 architecture, contracts, and ADRs](Resources/doc/v2-implementation-plan.md)
- [Upgrade from 1.x](UPGRADE-2.0.md)
- [Changelog](CHANGELOG.md)

## Adapters

The core ships session or signed-cookie persistence and request/execution context. Optional adapters cover application callables (`CallableTimezoneResolver`), authenticated users, explicit OIDC claims, MaxMind City databases or readers, browser timezone sync, Twig, Symfony Form, Messenger, the web profiler, and console diagnostics. Integrations activate only when configured and available; see the installation guide for their exact wiring.

## Quality

```bash
composer validate --strict --no-check-publish
composer analyse
composer test
composer check
npm test
```

`composer check` runs PHPStan and PHPUnit. The Node test covers the dependency-free browser ES module.
