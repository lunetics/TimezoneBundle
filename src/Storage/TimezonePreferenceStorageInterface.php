<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TimezonePreferenceStorageInterface
{
    /**
     * Request attribute set to true when a preference write succeeded during
     * the current request lifecycle. The bundle maintains it by decorating the
     * configured storage (PreferenceWriteMarkingStorage), so implementations
     * do not need to set it themselves — writes only have to go through the
     * storage service the bundle wires. The invalid-preference cleanup
     * listener skips its response-time cleanup when the marker is present on
     * the main request.
     */
    public const PREFERENCE_WRITTEN_ATTRIBUTE = '_lunetics_timezone.preference_written';

    /** @throws PersistenceFailureExceptionInterface */
    public function read(Request $request): TimezonePreferenceRead;

    /** @throws PersistenceFailureExceptionInterface */
    public function write(Request $request, Response $response, TimezonePreference $preference): void;

    /** @throws PersistenceFailureExceptionInterface */
    public function clear(Request $request, Response $response): void;
}
