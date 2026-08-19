# 4.0 technical plan, ADR, and contracts

**Status: implemented and current (July 2026).** This is the sole authoritative technical plan and architecture decision record for 4.0. User-facing setup remains in [installation.md](installation.md).

## Goals and non-goals

4.0 provides deterministic per-request IANA timezone resolution; validated value objects; session, cookie, or custom persistence; an application-facing current-timezone provider; bounded execution context; browser synchronization; optional user, OIDC, MaxMind City, locale, Twig, Form, Messenger, profiler, and console adapters; and redacted diagnostics.

`CallableTimezoneResolver` is the shipped convenience boundary for application `callable(Request): mixed` resolvers. It requires a safe lowercase source matching `^[a-z][a-z0-9_.-]{0,99}$`, defaults its kind to `INFERRED`, passes through `null` and `TimezoneResolution`, validates strings through `TimezoneId`, wraps `TimezoneId` with its configured source/kind, and rejects unsupported results with `TimezoneResolverException`. `InvalidTimezoneException` and `TimezoneResolverException` follow `resolution.failure_strategy`; deliberately thrown `TimezoneResolverException` is therefore policy-controlled, while unrelated exceptions and errors bubble. The adapter is not auto-registered and requires the same explicit resolver tag as any custom resolver.

It does not change PHP's process-global timezone, convert stored timestamps, authenticate OIDC tokens, update timezone/GeoIP databases, or provide Doctrine, Redis, vendor-specific OIDC, API Platform, Stimulus, timestamp-conversion, Country/ASN MaxMind, or other vendor persistence adapters. It is one Composer package, not a core-plus-bridge package family.

## Support policy and forward compatibility

The package requires PHP `^8.3` and Symfony components `^7.4.13 || ^8.1`. As of July 2026, Symfony 8.1 is the primary current target and 7.4 is the current LTS; Composer `^8.1` deliberately admits forward-compatible Symfony 8.x minors.

The range deliberately tracks maintained lines rather than every installable one. Symfony 8.0 left support in July 2026, Symfony 6.4 stops receiving bug fixes in November 2026 (security-only until November 2027), and PHP 8.2 reaches end of life in December 2026 — a new major released now would ship with a floor that dies within months. The `7.4.13` floor additionally excludes the versions affected by CVE-2026-48736 in `symfony/http-foundation`, whose `IpUtils::PRIVATE_SUBNETS` omits the 6to4 and NAT64 transition prefixes. Applications on Symfony 6.4 stay on the legacy 3.0 line, which 4.0 breaks from regardless.

Optional integrations fail clearly when explicitly enabled without their component, while `auto` integrations activate only when their framework extension/service is present.

Public interfaces and value semantics listed below are the compatibility surface. Internal service construction and listener wiring may evolve without being treated as public API. 4.0 is a clean break from the legacy 2.1/3.0 line and contains no backward-compatibility layer.

## Architecture and data flow

For a main `kernel.request`, `ResolveTimezoneListener` invokes `TimezoneResolverChain`. The chain tries tagged resolvers in policy order, records every attempt in `TimezoneResolutionTrace`, selects the first result, or creates a synthetic configured-default result. It stores the resolution on the main request and dispatches `TimezoneResolvedEvent`. Subrequests do not resolve independently.

`CurrentTimezoneProvider` computes current state in this exact precedence:

1. active execution context;
2. resolution attached to the current main request;
3. configured default.

Browser writes and invalid-preference cleanup use the response-aware storage contract. Twig temporarily applies the selected timezone to Twig only. Messenger stamps dispatches and establishes a bounded execution context while a worker middleware continues the stack. None of these paths changes PHP's global timezone.

## Resolver policy

The default order is request attribute `1000`, header `950`, stored manual fixed `925`, user `900`, OIDC `850`, stored browser fixed `800`, MaxMind `400`, locale mapping `200`, locale unique-country `100`, then the synthetic configured default last. Disabled resolvers are absent. The locale resolver returns a value only when the locale country has exactly one PHP timezone.

The OIDC resolver's public resolution source is `oidc_zoneinfo` for the default `zoneinfo` claim. Any customized claim uses `oidc_claim_<hash>`, where `<hash>` is the first 16 lowercase hexadecimal SHA-256 characters of the exact case-sensitive configured claim name. This bounded hash keeps raw/custom claim names out of diagnostics while remaining stable.

Higher priority runs first. Equal priority preserves service registration order (FIFO). Resolver services require the explicit `lunetics_timezone.resolver` tag; `TimezoneResolverInterface` is deliberately not auto-tagged. Every tag occurrence requires a non-empty, unique string `index`; `priority` is optional and defaults to `0` through Symfony's tagged iterator behavior. Missing, invalid, or duplicate tag indices are rejected at container compilation. Stable indices are used in traces and diagnostics. Stored manual and browser resolvers use distinct source filters. Manual preferences outrank and suppress browser preferences; the browser endpoint will not overwrite a manual record.

## Public contracts

The signatures below are copied from the implemented source (imports omitted only for readability).

```php
interface TimezoneResolverInterface
{
    public function resolve(Request $request): ?TimezoneResolution;
}

interface TimezonePreferenceStorageInterface
{
    public function read(Request $request): TimezonePreferenceRead;
    public function write(Request $request, Response $response, TimezonePreference $preference): void;
    public function clear(Request $request, Response $response): void;
}

interface CurrentTimezoneProviderInterface
{
    public function getResolution(): TimezoneResolution;
    public function getResolutionForRequest(Request $request): TimezoneResolution;
    public function getTimezone(): TimezoneId;
    public function getDateTimeZone(): \DateTimeZone;
}

interface TimezoneExecutionContextInterface
{
    public function run(TimezoneId $timezone, callable $callback): mixed;
    public function current(): ?TimezoneId;
}

interface TimezoneAwareUserInterface
{
    public function getTimezone(): TimezoneId|string|null;
}

interface UserTimezoneAccessorInterface
{
    public function getTimezoneForUser(object $user): TimezoneId|string|null;
}

interface OidcClaimsProviderInterface
{
    /** @return array<string, mixed> */
    public function claimsForRequest(Request $request): array;
}

interface MaxMindCityReaderInterface
{
    public function timezoneForIp(string $ipAddress): TimezoneId|string|null;
}

interface CountryTimezoneSourceInterface
{
    /** @return list<TimezoneId> */
    public function forCountry(string $countryCode): array;
}
```

Core public value/event construction is:

```php
TimezoneId::fromString(string $value): TimezoneId;
TimezoneId::fromDateTimeZone(\DateTimeZone $timezone): TimezoneId;
TimezoneId::value(): string;
TimezoneId::toDateTimeZone(): \DateTimeZone;
TimezoneId::equals(TimezoneId $other): bool;

new TimezoneResolution(TimezoneId $timezone, string $source, ResolutionKind $kind);
new TimezonePreference(TimezoneId $timezone, PreferenceSource $source, \DateTimeImmutable $recordedAt);
new TimezonePreferenceRead(PreferenceReadStatus $status, ?TimezonePreference $preference = null);
new TimezoneResolutionAttempt(string $resolver, ResolutionAttemptOutcome $outcome, ?string $source = null, int $durationMicroseconds = 0);
new TimezoneResolutionTrace(array $attempts, ?TimezoneResolution $selected);
new TimezoneResolvedEvent(Request $request, TimezoneResolution $resolution);
new TimezonePreferenceChangedEvent(Request $request, ?TimezonePreference $previous, ?TimezonePreference $current);
new TimezoneStamp(string $timezone);
```

`ResolutionKind` values are `explicit`, `authenticated`, `persisted`, `inferred`, and `default`. Attempt outcomes are `no_result`, `resolved`, `invalid`, `failed`, and `defaulted`. Preference sources are only `manual` and `browser`; read statuses are `absent`, `valid`, `invalid`, and `expired`. Resolution and persistence failure interfaces are separate throwable markers.

## Persistence format and security

Every built-in storage envelope has exactly these keys in order: `v`, `timezone`, `source`, `recorded_at`. Version is integer `1`; timezone is a validated IANA identifier; source is `manual|browser`; recording time is an ISO-8601/ATOM string. Session storage stores this array under its configured key and does not start a missing session merely to read or clear it.

Cookie storage serializes the same versioned envelope as JSON, base64url-encodes it, and appends a base64url HMAC-SHA-256 signature over the encoded payload. The HMAC key is derived from the configured secret using HKDF-SHA-256, length 32, info `lunetics-timezone-cookie-v1`. Reads use constant-time signature comparison, enforce encoded-size, schema/version, timezone/source/time, future-skew, and maximum-age checks, and classify bad input without exposing it. Cookie settings cover name, age, path, domain, secure, HttpOnly, SameSite, skew, and maximum size. `SameSite=None`, `__Host-`, and `__Secure-` require explicit `secure=true`; `__Host-` also requires `/` and no domain.

Custom storage is selected by service ID and must implement the exact request/response-aware interface. 4.0 ships no Doctrine or Redis implementation.

Session storage reads and cleanup do not start a missing session. Writes require Framework session configuration; if the request has no session, the storage raises a typed failure and the browser endpoint returns `503`.

## Browser route protocol

Browser support defaults off. Enabling it registers the controller but applications must opt in to routing by importing `@LuneticsTimezoneBundle/Resources/config/routes.php` with type `php`. The POST route is `/_lunetics/timezone/browser`. The asset is the pure ES module `bundles/luneticstimezone/timezone.js`; it requires no Stimulus or build tool.

The request content type must be `application/json` or an `application/*+json` type, body size at most 1024 bytes, and body exactly one string field: `{"timezone":"<IANA ID>"}`. CSRF defaults enabled with token ID `lunetics_timezone.preference` and header `X-CSRF-Token`. The controller returns `400` for malformed JSON/shape, `403` for CSRF failure, `413` for excess size, `415` for content type, `422` for invalid timezone, `503` for persistence failure, and `204` for written, unchanged, or manual-suppressed success. There is no Symfony Validator dependency.

The module reads `Intl.DateTimeFormat().resolvedOptions().timeZone`, avoids a request when absent or equal to the supplied stored browser timezone, and accepts `csrfHeader` alongside `csrfToken` so applications can pass the configured `browser.csrf.header` (default `X-CSRF-Token`). It posts with same-origin credentials, emits `lunetics:timezone-synced` only on success, and resolves false on HTTP/network failure.

## Shipped adapters and explicit exclusions

- Request attribute, trusted header (`framework|allowlist|any`, with `any` explicitly unsafe), stored manual/browser, authenticated user, OIDC claim, MaxMind City, locale mapping, and unique-country locale resolvers.
- `CallableTimezoneResolver` for explicitly registered application callables.
- Session and signed-cookie storage plus the custom storage contract.
- `TimezoneAwareUserInterface` or an explicit `UserTimezoneAccessorInterface`; no reflective property/method discovery.
- Explicit `OidcClaimsProviderInterface`, default claim `zoneinfo`; no token verification or vendor-specific provider.
- GeoLite2 City and GeoIP2 City through `GeoIp2\\Database\\Reader::city()`. Country and ASN are unsupported. GeoIP2 Enterprise is available only through a custom `MaxMindCityReaderInterface` service or `CallableMaxMindCityReader` adapter.
- Browser ES module/controller, Twig, Form, Messenger, profiler, `debug:timezone`, and local-database `timezone:check`.

Not shipped: Doctrine, Redis, vendor OIDC, API Platform, Stimulus, timestamp conversion, MaxMind Country/ASN, a general Enterprise database adapter, or recurring browser automation infrastructure.

## Twig, Form, and Messenger lifecycle

Twig integration obtains Twig's `CoreExtension`, pushes its prior timezone when `TimezoneResolvedEvent` fires, and restores it on main `kernel.finish_request`. `kernel.terminate` and reset provide cleanup; nested scope operations are stack-based and `run()` restores in `finally`.

Form integration extends `DateTimeType`, `DateType`, and `TimeType`, setting the default `view_timezone` from the current provider. It does not alter model timezone or perform storage conversion.

Messenger integration is opt-in and exposes public service IDs `lunetics_timezone.messenger.dispatch_middleware` and `lunetics_timezone.messenger.worker_middleware`. Users place these on their bus(es); the bundle does not inspect or mutate bus middleware. Dispatch middleware adds a validated `TimezoneStamp` only when absent. Worker middleware uses the last stamp, falling back to the provider, and runs downstream handling inside `TimezoneExecutionContextInterface::run()` so cleanup occurs in `finally`.

## Diagnostics, failures, and logging

`TimezoneResolutionTrace` is attached to the request and contains ordered resolver name, outcome, selected source, and duration. The debug command shows configured default/order. The profiler collector shows the effective result, configured resolvers, attempts, and preference read/write/cleanup flags; its normalized data survives Symfony profile serialization and reload. The MaxMind check command verifies that a configured local database is readable and has a City database type.

`resolution.failure_strategy` and `persistence.failure_strategy` are separate `continue|throw` decisions. In continue mode, typed resolver failures are traced/logged and the chain continues; storage reads resolve as no result when configured to continue, and failed invalid-record cleanup is ignored. Throw mode propagates the typed failure. Browser persistence failures map to `503` regardless because the write did not succeed.

Resolver logging uses PSR-3 levels and structured fields: resolver, outcome, duration, and, only for a selected validated result, source, kind, and timezone. It deliberately excludes raw header values, IPs, OIDC claims, user objects, cookies, session payloads, secrets, and exception text. Custom adapters must retain this redaction boundary.

## Testing and CI policy

PHPUnit covers value objects, resolver ordering/ties/failures, shipped resolvers, storage tamper/expiry/security behavior, controller protocol/statuses, context cleanup, Twig/Form/Messenger adapters, profiler/commands, compiler validation, and real-kernel smoke paths. Kernel coverage exercises both accepted and rejected CSRF tokens, the sessionless browser-write `503`, automatic bundle AssetMapper discovery without manually configured asset paths, and profiler serialization/reload. PHPStan is required. CI runs the supported Symfony/PHP combinations—PHP 8.3/8.4 with Symfony 7.4 and PHP 8.4/8.5 with Symfony 8.1—plus prefer-lowest with a production-dependency audit, `composer validate --strict --no-check-publish`, PHPStan, PHPUnit, and a clean `composer --no-dev` package smoke. The target is meaningful contract and branch-boundary coverage, not a promise of full branch coverage.

The pure module has a small Node test. Browser automation is optional, one-time/local release-confidence work; recurring browser infrastructure or CI is intentionally not required.

Distribution verification uses the Composer export/archive view: excluded development material must stay excluded, while documentation linked from the public entry points—including [scope.md](scope.md) and this plan—and the root [LICENSE](../../LICENSE) must remain in the archive.

## Accepted ADRs and consequences

1. **Use `TimezoneId`, not loose strings internally.** Invalid IDs fail at boundaries; adapters must convert explicitly.
2. **Resolve per request without changing the PHP default.** Concurrent and long-running workers avoid shared global timezone mutation; callers use the provider/context.
3. **Order explicit tagged resolvers.** Applications can insert policy predictably; they must choose unique stable indices and understand priorities.
4. **Keep manual and browser persistence sources distinct.** Explicit choice remains authoritative; storage format includes source and browser sync cannot overwrite manual state.
5. **Make routing and active integrations opt-in.** Installation has fewer hidden routes/bus changes; applications wire routes and Messenger middleware deliberately.
6. **Sign cookie state with a derived key and versioned schema.** Client persistence is tamper-evident and migratable; operators must supply/rotate a secret with awareness that old cookies become invalid.
7. **Use explicit user/OIDC/MaxMind boundaries.** No reflection or vendor coupling; applications write small adapters when their domain differs.
8. **Separate resolution from persistence failure policy.** Availability choices can differ; operators must configure both intentionally.
9. **Keep diagnostics structured and redacted.** Traces remain useful without leaking identity/network/session material; custom resolvers should use safe identifiers.
10. **Ship one Composer package.** Versioning and installation stay simple; optional code is guarded by configuration/component availability.
11. **Keep clock ownership scoped.** The internal `lunetics_timezone.clock` alias is selected after all extensions: it uses an existing application `Psr\Clock\ClockInterface`, otherwise `SystemClock`; the bundle never defines or replaces the application's global clock alias.
12. **Reject ambiguous configuration early.** Unknown integration keys, invalid/blank cookie or MaxMind identifiers, and configured-service alias cycles fail during container compilation.

## Migration and release checklist

- Remove every legacy Guesser, manager, event, listener, provider, and bundle-validator reference; there is no BC layer.
- Replace application access with `CurrentTimezoneProviderInterface`; replace guessers with explicitly tagged `TimezoneResolverInterface` services.
- Choose session, signed cookie, or custom storage and migrate/discard old persisted values; 4.0 expects the version-1 envelope.
- Implement user/OIDC/MaxMind contracts explicitly where required.
- Import the browser route only if enabled, provide CSRF token/header, and load the pure ES module.
- Add both Messenger middleware service IDs to the intended bus configuration; verify ordering around send/handle middleware.
- Review header trust, cookie `secure`/SameSite/`__Host-`, failure strategies, and logging redaction.
- Run `composer validate --strict --no-check-publish`, `composer check`, `npm test`, the local kernel integration test, and targeted stale-API searches.
- Confirm README/docs links, changelog, upgrade guide, package contents, and supported dependency matrix before tagging 4.0.0.
