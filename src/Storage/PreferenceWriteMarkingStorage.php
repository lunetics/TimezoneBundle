<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates the configured storage so the preference-written marker is
 * maintained by the bundle for every implementation: the marker lands on the
 * request that wrote and, for lifecycle-scoped storage, on the main request as
 * well, so a subrequest write is visible to the main-request cleanup.
 * Response-scoped storage (see {@see ResponseScopedStorageInterface}) is
 * deliberately not propagated across requests.
 */
final readonly class PreferenceWriteMarkingStorage implements TimezonePreferenceStorageInterface
{
    public function __construct(private TimezonePreferenceStorageInterface $inner, private RequestStack $requestStack)
    {
    }

    public function read(Request $request): TimezonePreferenceRead
    {
        return $this->inner->read($request);
    }

    public function write(Request $request, Response $response, TimezonePreference $preference): void
    {
        $this->inner->write($request, $response, $preference);
        $request->attributes->set(self::PREFERENCE_WRITTEN_ATTRIBUTE, true);
        if ($this->inner instanceof ResponseScopedStorageInterface) {
            // The write lives on this response only. Suppressing the cleanup
            // on a different response would leave the stale record in place
            // while the fresh one never reaches the client; the storage
            // protects its own response instead.
            return;
        }
        $main = $this->requestStack->getMainRequest();
        if (null !== $main && $main !== $request) {
            $main->attributes->set(self::PREFERENCE_WRITTEN_ATTRIBUTE, true);
        }
    }

    public function clear(Request $request, Response $response): void
    {
        $this->inner->clear($request, $response);
    }
}
