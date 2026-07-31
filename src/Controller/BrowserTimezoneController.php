<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Controller;

use Lunetics\TimezoneBundle\Event\TimezonePreferenceChangedEvent;
use Lunetics\TimezoneBundle\Exception\PersistenceFailureExceptionInterface;
use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class BrowserTimezoneController
{
    public const PREFERENCE_WRITTEN_ATTRIBUTE = TimezonePreferenceStorageInterface::PREFERENCE_WRITTEN_ATTRIBUTE;

    public function __construct(
        private TimezonePreferenceStorageInterface $storage,
        private ClockInterface $clock,
        private EventDispatcherInterface $dispatcher,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private string $csrfTokenId = 'lunetics_timezone.preference',
        private string $csrfHeader = 'X-CSRF-Token',
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->isJsonContentType($request->headers->get('Content-Type'))) {
            return new Response('', Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }
        $content = $request->getContent();
        if (strlen($content) > 1024) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }
        if (null !== $this->csrfTokenManager && !$this->csrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenId, (string) $request->headers->get($this->csrfHeader, '')))) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }
        try {
            $data = json_decode($content, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }
        if (!is_array($data) || array_is_list($data) || array_keys($data) !== ['timezone'] || !is_string($data['timezone'])) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }
        try {
            $timezone = TimezoneId::fromString($data['timezone']);
        } catch (InvalidTimezoneException) {
            return new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $response = new Response('', Response::HTTP_NO_CONTENT);
        try {
            $read = $this->storage->read($request);
            $previous = PreferenceReadStatus::VALID === $read->status ? $read->preference : null;
            if (PreferenceSource::MANUAL === $previous?->source) {
                return $response;
            }
            $current = new TimezonePreference($timezone, PreferenceSource::BROWSER, \DateTimeImmutable::createFromInterface($this->clock->now()));
            if (null !== $previous && $previous->equals($current)) {
                return $response;
            }
            $this->storage->write($request, $response, $current);
        } catch (PersistenceFailureExceptionInterface) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $this->dispatcher->dispatch(new TimezonePreferenceChangedEvent($request, $previous, $current));
        return $response;
    }

    private function isJsonContentType(?string $contentType): bool
    {
        if (null === $contentType) {
            return false;
        }
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        return 'application/json' === $mediaType || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'));
    }
}
