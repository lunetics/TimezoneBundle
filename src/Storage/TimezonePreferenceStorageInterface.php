<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TimezonePreferenceStorageInterface
{
    /**
     * Request attribute marking that a preference write succeeded during the
     * current request, regardless of who triggered it. Implementations must
     * set it to true inside write(); the invalid-preference cleanup listener
     * skips its response-time cleanup when the marker is present so a freshly
     * written preference is never cleared again within the same request.
     */
    public const PREFERENCE_WRITTEN_ATTRIBUTE = '_lunetics_timezone.preference_written';

    /** @throws PersistenceFailureExceptionInterface */
    public function read(Request $request): TimezonePreferenceRead;

    /** @throws PersistenceFailureExceptionInterface */
    public function write(Request $request, Response $response, TimezonePreference $preference): void;

    /** @throws PersistenceFailureExceptionInterface */
    public function clear(Request $request, Response $response): void;
}
