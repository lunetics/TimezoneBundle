# Changelog

## 4.0.0 — 2026-08-19

- Replaced the legacy Guesser/Event API with ordered, explicitly tagged timezone resolvers and traceable resolution results.
- Added validated `TimezoneId`, per-request current-timezone provider, and bounded execution context without global timezone mutation.
- Added versioned session persistence, signed HMAC/HKDF cookie persistence, and a request/response-aware custom storage contract.
- Added opt-in browser timezone synchronization with CSRF, strict JSON protocol, and a dependency-free ES module.
- Added explicit authenticated-user and OIDC contracts, MaxMind City support, and locale mapping/unique-country inference.
- Added optional Twig, Form, Messenger, profiler, and console integrations. Messenger middleware is published for application bus configuration and does not mutate buses.
- Added independent resolution and persistence failure strategies, structured redacted logging, diagnostics, supported-version CI, and 4.0 migration documentation.
- Added the explicitly registered `CallableTimezoneResolver` convenience adapter for application callables, with typed policy-controlled failures and strict result/source validation.
- Hardened container compilation: integration keys are strict; cookie and MaxMind identifiers are validated; aware-user/accessor exceptions become typed resolver failures; and configured-service alias cycles are rejected.
- Scoped clock selection to the bundle's internal alias without defining or replacing the application's global PSR clock alias.
- Hardened browser/session and diagnostics behavior: sessionless session-storage writes return `503`, and profiler data survives profile serialization/reload.
- Hardened distribution and CI checks with automatic bundle AssetMapper discovery, clean Composer `--no-dev` smoke coverage, a production-dependency audit at the dependency floor, and archive retention of linked scope/plan docs and the root `LICENSE`.
- Requires PHP `^8.3` and Symfony `^7.4.13 || ^8.1`: unmaintained Symfony 8.0, the older 6.4 LTS, and end-of-life PHP 8.2 are outside the supported range, and the floor excludes the versions affected by CVE-2026-48736.
- Removed the bundle Validator integration and the Symfony Validator requirement. 4.0 has no legacy compatibility layer.
