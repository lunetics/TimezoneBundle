<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TimezonePreferenceStorageInterface
{
    /** @throws PersistenceFailureExceptionInterface */
    public function read(Request $request): TimezonePreferenceRead;

    /** @throws PersistenceFailureExceptionInterface */
    public function write(Request $request, Response $response, TimezonePreference $preference): void;

    /** @throws PersistenceFailureExceptionInterface */
    public function clear(Request $request, Response $response): void;
}
