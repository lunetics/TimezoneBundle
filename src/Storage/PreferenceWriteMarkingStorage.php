<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates the configured storage so the preference-written marker is
 * maintained by the bundle for every implementation and every request
 * lifecycle: the marker lands on the request that wrote AND on the main
 * request, so a subrequest write is visible to the main-request cleanup.
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
