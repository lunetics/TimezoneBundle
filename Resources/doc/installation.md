# Installation and configuration

## Requirements and registration

Install PHP `^8.2` and Symfony `^6.4 || ^7.4 || ^8.0`, then:

```bash
composer require lunetics/timezone-bundle
```

If Flex did not register it, add `Lunetics\TimezoneBundle\LuneticsTimezoneBundle::class => ['all' => true]` to `config/bundles.php`.

No Symfony Validator component is required. The browser adapter is a pure ES module and requires neither Stimulus nor a JavaScript build tool.

## Complete configuration

This tree shows every option and default:

```yaml
lunetics_timezone:
    default_timezone: UTC
    resolution:
        failure_strategy: continue # continue|throw
        request_attribute:
            enabled: true
            attribute: _timezone
            priority: 1000
        header:
            enabled: false
            name: X-Timezone
            trust: framework       # framework|allowlist|any
            trusted_sources: []
            priority: 950
        user:
            enabled: auto          # auto|true|false
            accessor: null
            priority: 900
        oidc:
            enabled: false
            service: null
            claim: zoneinfo
            priority: 850
        maxmind:
            enabled: false
            database: null
            reader: null
            priority: 400
        locale_mapping:
            enabled: auto          # auto|true|false
            mapping: {}
            priority: 200
        locale:
            enabled: true
            priority: 100
    persistence:
        storage: session           # session|cookie|service-id
        failure_strategy: continue # continue|throw
        session:
            key: _lunetics_timezone
        cookie:
            name: _lunetics_timezone
            max_age: 31536000
            path: /
            domain: null
            secure: auto           # auto|true|false
            http_only: true
            same_site: lax         # lax|strict|none
            secret: null
            future_skew: 60
            max_size: 4096
    browser:
        enabled: false
        csrf:
            enabled: true
            token_id: lunetics_timezone.preference
            header: X-CSRF-Token
    integrations:
        twig: auto                 # auto|true|false
        form: false
        messenger: false
        profiler: auto             # auto|true|false
```

`header.trust: framework` accepts the header only from requests Symfony identifies as coming through a trusted proxy. `allowlist` requires at least one exact IP/CIDR in `trusted_sources`. `any` trusts every client and is explicitly unsafe unless an upstream boundary removes and rewrites the header.

Configuration is strict at container-build time. Unknown keys under `integrations` are rejected. The cookie name must be a valid cookie identifier, and configured MaxMind `database`/`reader` identifiers must be non-blank; invalid or blank values fail while the container is built rather than at first use.

Cookie storage requires `cookie.secret`. Its versioned JSON value is signed; invalid and expired values are ignored and cleared on the response. `same_site: none` requires the explicit setting `secure: true`. A `__Host-` name likewise requires explicit `secure: true`, `path: /`, and `domain: null`; a `__Secure-` name requires explicit `secure: true`. `secure: auto` is not sufficient for any of these rules.

## Browser synchronization and route opt-in

Enabling the service does not add a route. Import it explicitly:

```yaml
# config/routes/lunetics_timezone.yaml
lunetics_timezone:
    resource: '@LuneticsTimezoneBundle/Resources/config/routes.php'
    type: php
```

With AssetMapper, import the shipped path and call it from your own module:

```js
import {syncBrowserTimezone} from 'bundles/luneticstimezone/timezone.js';

syncBrowserTimezone({
    endpoint: '/_lunetics/timezone/browser',
    csrfToken: document.querySelector('meta[name="timezone-csrf"]')?.content,
    csrfHeader: 'X-CSRF-Token',
    storedBrowserTimezone: null,
});
```

Render the CSRF token for the configured token ID, for example `<meta name="timezone-csrf" content="{{ csrf_token('lunetics_timezone.preference') }}">`. Pass the configured `browser.csrf.header` as `csrfHeader`; both default to `X-CSRF-Token`. The module POSTs exactly `{"timezone":"Europe/Berlin"}` as JSON, sends the token in that header, and emits `lunetics:timezone-synced` after a successful response. CSRF is enabled by default and requires Symfony CSRF protection. The endpoint uses `204` for success/no change, `400` for malformed JSON or shape, `403` for invalid CSRF, `413` above 1024 bytes, `415` for a non-JSON content type, `422` for an invalid timezone, and `503` on persistence failure. A manual preference is never overwritten by browser sync.

When `persistence.storage: session` is used, browser writes require Framework session configuration so the request has a session. A missing session raises the bundle's typed storage failure at the storage boundary and the browser endpoint returns `503`.

## Provider and execution context

Inject `Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface`. Its `getTimezone()` returns `TimezoneId`, `getDateTimeZone()` returns `\DateTimeZone`, and `getResolution()` includes source and kind. `TimezoneExecutionContextInterface::run(TimezoneId $timezone, callable $callback): mixed` temporarily overrides the current timezone for a bounded callback; `current(): ?TimezoneId` exposes the innermost active scope.

## Users and OIDC

With Symfony Security available, `resolution.user.enabled: auto` enables user resolution. Either make the authenticated user implement:

```php
Lunetics\TimezoneBundle\Contract\User\TimezoneAwareUserInterface::getTimezone(): TimezoneId|string|null
```

or configure an accessor service implementing:

```php
Lunetics\TimezoneBundle\Contract\User\UserTimezoneAccessorInterface::getTimezoneForUser(object $user): TimezoneId|string|null
```

Set its service ID in `resolution.user.accessor`. OIDC is separate and explicit: enable it, set `resolution.oidc.service` to a service implementing `OidcClaimsProviderInterface::claimsForRequest(Request $request): array`, and optionally change the `zoneinfo` claim name. The public resolution source is `oidc_zoneinfo` for the default `zoneinfo` claim. Any customized claim uses `oidc_claim_<hash>`, where `<hash>` is the first 16 lowercase hexadecimal SHA-256 characters of the exact case-sensitive configured claim name. This bounded hash keeps raw/custom claim names out of diagnostics while remaining stable. The provider supplies already verified claims; the bundle uses no reflection and does not authenticate tokens.

## Custom resolver and storage

Custom resolvers implement `TimezoneResolverInterface::resolve(Request $request): ?TimezoneResolution` and require an explicit `lunetics_timezone.resolver` tag with a unique `index` and optional `priority`. The interface is deliberately not auto-tagged. See [resolver precedence](resolvers.md).

The shipped `CallableTimezoneResolver` adapts a `callable(Request): mixed` and is likewise registered explicitly; it is useful for application methods such as `['@App\Timezone\AccountTimezoneLookup', 'resolve']`. See [resolver precedence](resolvers.md) for the complete constructor, return conversion, failure semantics, and YAML service definition.

For custom persistence, set `persistence.storage` to a service ID implementing:

```php
public function read(Request $request): TimezonePreferenceRead;
public function write(Request $request, Response $response, TimezonePreference $preference): void;
public function clear(Request $request, Response $response): void;
```

The storage must preserve the preference timezone, `manual|browser` source, and recording time. Built-in session storage stores the envelope directly; cookie storage encodes a signed versioned JSON envelope.

The bundle decorates the configured storage with `PreferenceWriteMarkingStorage`, which records every successful `write()` on the current AND the main request (`TimezonePreferenceStorageInterface::PREFERENCE_WRITTEN_ATTRIBUTE`). The cleanup listener clears invalid or expired stored preferences on the response and skips that cleanup when the marker is present — so an application write (for example a settings form persisting a `manual` preference, even from a subrequest) survives the same-request cleanup. Custom storages do not need to set the marker themselves; writes only have to go through the storage service the bundle wires (inject `TimezonePreferenceStorageInterface`, not your concrete storage class).

Storage that carries its writes on the `Response` instead of a lifecycle-wide backend — cookie storage, and any custom storage implementing `ResponseScopedStorageInterface` — is treated differently on subrequests: its marker is not propagated to the main request, because a fragment response is discarded before it reaches the client. The cleanup then still removes the stale record, and the storage refuses to clear a preference it has just written to the same response.

## MaxMind City

Install `geoip2/geoip2` and point `resolution.maxmind.database` at a GeoLite2 City or GeoIP2 City database. Both use the reader's `city()` lookup. Country and ASN databases are unsupported. GeoIP2 Enterprise is supported only across the custom reader boundary: configure `resolution.maxmind.reader` with a service implementing `MaxMindCityReaderInterface::timezoneForIp(string $ipAddress): TimezoneId|string|null`, or adapt a callable with `CallableMaxMindCityReader`. Exactly one of `database` and `reader` is required when enabled.

## Framework integrations and diagnostics

- Twig (`auto`) sets Twig's CoreExtension timezone after main-request resolution and restores the previous value on finish/terminate/reset, including nested stack handling.
- Form (`false`) defaults `view_timezone` on `DateTimeType`, `DateType`, and `TimeType` to the current timezone.
- Messenger (`false`) publishes `lunetics_timezone.messenger.dispatch_middleware` and `lunetics_timezone.messenger.worker_middleware`. Add them to the appropriate bus middleware configuration yourself; the bundle does not mutate buses. Dispatch adds `TimezoneStamp`; worker handling runs downstream middleware inside the stamped execution context.
- Profiler (`auto`) adds the timezone collector in a debug kernel when WebProfilerBundle is active.
- `debug:timezone` prints the configured default and resolver order when Console is installed. `timezone:check` is available for a configured local MaxMind City database.

For example, wire both Messenger services on a bus whose dispatches and handlers should carry timezone context:

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - lunetics_timezone.messenger.dispatch_middleware
                    - lunetics_timezone.messenger.worker_middleware
```

Place them according to your send/handle topology if dispatch and worker handling use different buses.

Profiler diagnostics are scalar/array data and survive Symfony profile serialization and later profile reload.

## Clock ownership

The bundle uses the internal service `lunetics_timezone.clock`. After all extensions have loaded, it points to an existing application `Psr\Clock\ClockInterface` service when one is available, or to the bundle's `SystemClock` fallback otherwise. The bundle never defines or replaces the application's global `Psr\Clock\ClockInterface` alias.

## Failures and logging

`resolution.failure_strategy` independently controls resolver failures; `persistence.failure_strategy` controls storage read/cleanup failures. `continue` records the failure and proceeds where possible, while `throw` propagates the typed failure. Browser writes always map persistence failures to `503`.

When a PSR-3 `logger` service exists, resolver attempts are logged with structured fields such as resolver, outcome, source, kind, timezone, and duration. Claims, user objects, headers, IP addresses, cookie/session contents, secrets, and exception messages are not logged. Keep the same redaction boundary in custom adapters.
