# Scope

Symfony already provides timezone validation/list building through PHP and framework components, plus timezone-aware form options. This bundle does not replace those pieces. It supplies the missing application policy around them: ordered per-user resolution, preference persistence, request and execution context, browser-to-server synchronization, Twig and asynchronous propagation, and resolution traces/diagnostics.

Non-goals are changing PHP's global default timezone, converting stored timestamps, replacing a clock or calendar library, managing timezone database updates, authenticating OIDC tokens, or shipping vendor-specific persistence and identity integrations. Doctrine, Redis, API Platform, vendor-specific OIDC, Stimulus, and timestamp-conversion adapters are intentionally outside the package.
