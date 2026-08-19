# Resolvers and precedence

The first resolver returning a `TimezoneResolution` wins. Defaults produce this order:

| Priority | Resolver | Notes |
| ---: | --- | --- |
| 1000 | request attribute | `_timezone` by default |
| 950 | trusted header | disabled by default |
| 925 | stored manual preference | fixed priority |
| 900 | authenticated user | `auto` by default |
| 850 | OIDC claim | disabled by default |
| 800 | stored browser preference | fixed priority |
| 400 | MaxMind City | disabled by default |
| 200 | locale mapping | active in `auto` only when mapping is non-empty |
| 100 | locale unique-country inference | enabled by default; resolves only when the country has exactly one timezone |
| last | synthetic configured default | always present and not a tagged resolver |

Manual storage suppresses a browser preference: the browser endpoint does not overwrite it, and the browser resolver only accepts browser-sourced records. Equal priorities retain registration order (FIFO). Every explicit tag `index` must be unique; duplicate indices are rejected while compiling the container.

The OIDC resolver's public resolution source is `oidc_zoneinfo` for the default `zoneinfo` claim. Any customized claim uses `oidc_claim_<hash>`, where `<hash>` is the first 16 lowercase hexadecimal SHA-256 characters of the exact case-sensitive configured claim name. This bounded hash keeps raw/custom claim names out of diagnostics while remaining stable.

## Custom resolver

Implement the deliberately non-auto-tagged contract:

```php
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class AccountTimezoneResolver implements TimezoneResolverInterface
{
    public function resolve(Request $request): ?TimezoneResolution
    {
        // Return null to let the next resolver run.
        return null;
    }
}
```

Register it with an explicit, unique diagnostic index and an optional priority (the Symfony tagged-iterator default is `0`):

```yaml
services:
    App\Timezone\AccountTimezoneResolver:
        tags:
            - { name: lunetics_timezone.resolver, index: account, priority: 875 }
```

Use a safe, stable `TimezoneResolution::$source` identifier. Invalid timezone values should surface as a bundle resolution failure so the configured `continue|throw` policy can apply.

For an existing application callable, the shipped `CallableTimezoneResolver` is a convenience adapter. Its constructor accepts `callable(Request): mixed`, a source matching the lowercase identifier pattern `^[a-z][a-z0-9_.-]{0,99}$`, and an optional `ResolutionKind` that defaults to `INFERRED`. It is not auto-registered. For example, given an invokable service method such as `AccountTimezoneLookup::resolve(Request $request)`, register the adapter explicitly:

```yaml
services:
    App\Timezone\AccountTimezoneLookup: ~

    App\Timezone\AccountTimezoneResolver:
        class: Lunetics\TimezoneBundle\Resolver\CallableTimezoneResolver
        arguments:
            $resolver: ['@App\Timezone\AccountTimezoneLookup', 'resolve']
            $source: account
            # $kind defaults to Lunetics\TimezoneBundle\Resolution\ResolutionKind::INFERRED
        tags:
            - { name: lunetics_timezone.resolver, index: account, priority: 875 }
```

The callable may return `null` to continue, a `TimezoneResolution` to pass it through unchanged, a string to validate through `TimezoneId`, or a `TimezoneId` to wrap with the configured source and kind. Any other return type raises `TimezoneResolverException`. Invalid strings raise `InvalidTimezoneException`. Those two typed resolution failures are governed by `resolution.failure_strategy`; an application callable may deliberately throw `TimezoneResolverException` to request that policy-controlled handling. Unrelated exceptions and errors intentionally bubble instead of being converted into resolver failures.
