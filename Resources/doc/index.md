# LuneticsTimezoneBundle 2.x

The bundle resolves one validated IANA timezone per main request and makes it available as request-scoped application state. Resolution is an ordered policy: explicit request input and manual preferences can outrank user/OIDC data, browser preferences, geographic data, locale inference, and the configured fallback.

Use it when an application needs a consistent current-user timezone across controllers, Twig rendering, Symfony forms, and Messenger handlers, with persistence and an inspectable resolution trace. It never changes PHP's global default timezone.

Start with [installation and configuration](installation.md), then see [resolver precedence](resolvers.md). [Scope](scope.md) explains what belongs in the bundle. The [V2 technical plan and ADR](v2-implementation-plan.md) is the authoritative implementation and contract reference; existing 1.x users should read [the upgrade guide](../../UPGRADE-2.0.md).

The public entry point for application code is `Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface`. Optional adapters support application callables through `CallableTimezoneResolver`, users, explicit OIDC claims, MaxMind City lookups, browser sync, Twig, Form, Messenger, the profiler, and diagnostics commands.
