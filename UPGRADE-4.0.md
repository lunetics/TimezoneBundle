# Upgrading to LuneticsTimezoneBundle 4.0

4.0 is a clean break. It contains no backward-compatibility layer, aliases, adapters, or deprecation bridge for the legacy 2.1/3.0 APIs. Remove old usage before upgrading and migrate persisted preference data explicitly or let 4.0 create new values.

## API migration

- Replace `TimezoneGuesserInterface` implementations with `Lunetics\TimezoneBundle\Resolver\TimezoneResolverInterface`. Register each service explicitly with `lunetics_timezone.resolver`, a unique `index`, and the intended `priority`.
- Replace `TimezoneGuesserManager`, `GeoTimezoneGuesser`, `LocaleTimezoneGuesser`, and `LocalemapperTimezoneGuesser` configuration with 4.0 resolver configuration or a custom resolver.
- Replace `TimezoneProvider\TimezoneProvider` injection with `Context\CurrentTimezoneProviderInterface`. Use `getTimezone()`, `getDateTimeZone()`, or `getResolution()`.
- Replace `TimezoneBundleEvents`, `FilterTimezoneEvent`, and old timezone listeners with `TimezoneResolvedEvent`, `TimezonePreferenceChangedEvent`, or a custom resolver as appropriate.
- Remove the bundle's old `Validator\Timezone` constraint and validator. 4.0 validates `TimezoneId` at its own boundaries and does not require Symfony Validator.
- Replace old guesser configuration with the `resolution`, `persistence`, `browser`, and `integrations` trees documented in [installation](Resources/doc/installation.md).

## Persistence and identity

4.0 storage implements `TimezonePreferenceStorageInterface`; its `read()` receives a `Request`, while `write()` and `clear()` receive both `Request` and `Response`. Built-in state uses the versioned envelope `v`, `timezone`, `source`, `recorded_at`. Old session/cookie values are not a supported 4.0 format. The configured storage service is decorated by the bundle (write tracking for the invalid-preference cleanup): type-hint `TimezonePreferenceStorageInterface` when injecting it — the concrete class id resolves to the decorator, so a concrete type-hint fails loudly instead of bypassing the tracking.

Authenticated users either implement `TimezoneAwareUserInterface::getTimezone(): TimezoneId|string|null` or use an explicit `UserTimezoneAccessorInterface`. OIDC claims require an explicit `OidcClaimsProviderInterface`; the default claim is `zoneinfo`, and no reflection/provider-specific integration remains. The public resolution source is `oidc_zoneinfo` for the default `zoneinfo` claim. Any customized claim uses `oidc_claim_<hash>`, where `<hash>` is the first 16 lowercase hexadecimal SHA-256 characters of the exact case-sensitive configured claim name. This bounded hash keeps raw/custom claim names out of diagnostics while remaining stable.

## Optional integrations

- Browser sync defaults off. Enable it, import `@LuneticsTimezoneBundle/Resources/config/routes.php` with type `php`, provide the CSRF token, and import `bundles/luneticstimezone/timezone.js` as a pure ES module.
- Messenger defaults off. Enable it and add `lunetics_timezone.messenger.dispatch_middleware` and `lunetics_timezone.messenger.worker_middleware` to your own bus configuration. The bundle never mutates buses.
- Form defaults off. Twig and profiler default to `auto`.
- MaxMind accepts GeoLite2 City or GeoIP2 City files, or an explicit custom City reader. Country/ASN databases are not supported.

## Compatibility-relevant hardening

- Configuration now rejects unknown `integrations` keys, invalid cookie identifiers, blank MaxMind database/reader identifiers, and configured-service alias cycles while building the container.
- The bundle's internal clock uses an existing application `Psr\Clock\ClockInterface` after all extensions have loaded or falls back to `SystemClock`; it no longer defines or replaces the application's global clock alias.
- Exceptions thrown by `TimezoneAwareUserInterface` and configured user accessors are converted to typed resolver failures, while unrelated callable-adapter exceptions/errors still bubble. `CallableTimezoneResolver` is shipped for explicitly tagged application callables; it is not auto-registered.
- Session-backed browser writes require Framework session configuration. A sessionless write is a typed storage failure and returns `503` from the browser endpoint.
- Profiler diagnostics survive profile serialization/reload. Distribution checks now cover clean `--no-dev` installation and retain linked scope/plan documentation plus the root `LICENSE` in archives; AssetMapper discovers the bundle asset without manual paths.

## Migration sequence

1. Remove all legacy bundle configuration and obsolete API imports.
2. Register the 4.0 bundle and start from the minimal configuration.
3. Port custom resolution and storage services to the exact 4.0 contracts.
4. Choose how old preferences are discarded or transformed into the 4.0 envelope.
5. Wire optional route, CSRF, asset, Twig/Form/Messenger, OIDC, and MaxMind features explicitly.
6. Verify resolver order with `bin/console debug:timezone` and exercise application requests, workers, and preference writes.

See the [authoritative 4.0 contracts and ADR](Resources/doc/v4-implementation-plan.md) for exact behavior.
