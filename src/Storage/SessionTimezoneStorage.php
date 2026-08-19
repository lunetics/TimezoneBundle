<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;

final readonly class SessionTimezoneStorage implements TimezonePreferenceStorageInterface
{
    public function __construct(private string $key = '_lunetics_timezone')
    {
        if ('' === $key) {
            throw new \InvalidArgumentException('The session key must not be empty.');
        }
    }

    public function read(Request $request): TimezonePreferenceRead
    {
        if (!$request->hasSession()) {
            return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
        }

        try {
            $session = $request->getSession();
            if (!$session->isStarted() && !$request->cookies->has($session->getName())) {
                return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
            }
            if (!$session->has($this->key)) {
                return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
            }
            $value = $session->get($this->key);
        } catch (\RuntimeException $failure) {
            throw TimezoneStorageException::operationFailed('read', $failure);
        }

        return $this->decode($value);
    }

    public function write(Request $request, Response $response, TimezonePreference $preference): void
    {
        unset($response);
        try {
            $request->getSession()->set($this->key, $this->encode($preference));
        } catch (SessionNotFoundException|\RuntimeException $failure) {
            throw TimezoneStorageException::operationFailed('write', $failure);
        }
    }

    public function clear(Request $request, Response $response): void
    {
        unset($response);
        if (!$request->hasSession()) {
            return;
        }
        try {
            $session = $request->getSession();
            if (!$session->isStarted() && !$request->cookies->has($session->getName())) {
                return;
            }
            $session->remove($this->key);
        } catch (\RuntimeException $failure) {
            throw TimezoneStorageException::operationFailed('clear', $failure);
        }
    }

    /** @return array{v: 1, timezone: string, source: string, recorded_at: string} */
    private function encode(TimezonePreference $preference): array
    {
        return ['v' => 1, 'timezone' => $preference->timezone->value(), 'source' => $preference->source->value, 'recorded_at' => $preference->recordedAt->format(\DateTimeInterface::ATOM)];
    }

    private function decode(mixed $value): TimezonePreferenceRead
    {
        if (!is_array($value) || array_keys($value) !== ['v', 'timezone', 'source', 'recorded_at'] || 1 !== $value['v'] || !is_string($value['timezone']) || !is_string($value['source']) || !is_string($value['recorded_at'])) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        try {
            $source = PreferenceSource::tryFrom($value['source']);
            if (null === $source) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            $recordedAt = self::parseAtom($value['recorded_at']);
            if (null === $recordedAt) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            return new TimezonePreferenceRead(PreferenceReadStatus::VALID, new TimezonePreference(TimezoneId::fromString($value['timezone']), $source, $recordedAt));
        } catch (\Exception) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
    }

    private static function parseAtom(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return false !== $date && $date->format(\DateTimeInterface::ATOM) === $value ? $date : null;
    }
}
