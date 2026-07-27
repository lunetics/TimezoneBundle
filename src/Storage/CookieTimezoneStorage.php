<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Storage;

use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CookieTimezoneStorage implements TimezonePreferenceStorageInterface
{
    private const MAX_ENCODED_SIZE = 4096;
    private string $key;
    /** @var Cookie::SAMESITE_LAX|Cookie::SAMESITE_STRICT|Cookie::SAMESITE_NONE */
    private string $sameSite;

    public function __construct(
        string $secret,
        private ClockInterface $clock,
        private string $name = '_lunetics_timezone',
        private int $maxAge = 31536000,
        private string $path = '/',
        private ?string $domain = null,
        private ?bool $secure = null,
        private bool $httpOnly = true,
        string $sameSite = Cookie::SAMESITE_LAX,
        private int $futureSkew = 60,
        private int $maxEncodedSize = self::MAX_ENCODED_SIZE,
    ) {
        if ('' === $secret || '' === $name || $maxAge <= 0 || $futureSkew < 0 || $maxEncodedSize <= 0) {
            throw new \InvalidArgumentException('Invalid timezone cookie configuration.');
        }
        Cookie::create($name, raw: true);
        if (!in_array($sameSite, [Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE], true)) {
            throw new \InvalidArgumentException('Invalid SameSite value.');
        }
        $this->sameSite = $sameSite;
        if (Cookie::SAMESITE_NONE === $sameSite && true !== $secure) {
            throw new \InvalidArgumentException('SameSite=None requires a secure cookie.');
        }
        if (str_starts_with($name, '__Host-') && ('/' !== $path || null !== $domain || true !== $secure)) {
            throw new \InvalidArgumentException('__Host- cookies require path /, no domain, and secure transport.');
        }
        if (str_starts_with($name, '__Secure-') && true !== $secure) {
            throw new \InvalidArgumentException('__Secure- cookies require secure transport.');
        }
        $key = hash_hkdf('sha256', $secret, 32, 'lunetics-timezone-cookie-v1');
        $this->key = $key;
    }

    public function read(Request $request): TimezonePreferenceRead
    {
        try {
            $encoded = $request->cookies->get($this->name);
        } catch (BadRequestException) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        if (null === $encoded) {
            return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT);
        }
        if (strlen($encoded) > $this->maxEncodedSize) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        $parts = explode('.', $encoded);
        if (2 !== count($parts)) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        [$payload64, $signature64] = $parts;
        $signature = $this->base64UrlDecode($signature64);
        if (null === $signature || !hash_equals(hash_hmac('sha256', $payload64, $this->key, true), $signature)) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        $payload = $this->base64UrlDecode($payload64);
        if (null === $payload) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
        try {
            $data = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_keys($data) !== ['v', 'timezone', 'source', 'recorded_at'] || 1 !== $data['v'] || !is_string($data['timezone']) || !is_string($data['source']) || !is_string($data['recorded_at'])) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            $source = PreferenceSource::tryFrom($data['source']);
            if (null === $source) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            $recordedAt = self::parseAtom($data['recorded_at']);
            if (null === $recordedAt) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            $now = $this->clock->now();
            if ($recordedAt->getTimestamp() > $now->getTimestamp() + $this->futureSkew) {
                return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
            }
            if ($recordedAt->getTimestamp() + $this->maxAge < $now->getTimestamp()) {
                return new TimezonePreferenceRead(PreferenceReadStatus::EXPIRED);
            }
            return new TimezonePreferenceRead(PreferenceReadStatus::VALID, new TimezonePreference(TimezoneId::fromString($data['timezone']), $source, $recordedAt));
        } catch (\JsonException) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        } catch (\Lunetics\TimezoneBundle\Exception\InvalidTimezoneException) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        } catch (\Exception) {
            return new TimezonePreferenceRead(PreferenceReadStatus::INVALID);
        }
    }

    public function write(Request $request, Response $response, TimezonePreference $preference): void
    {
        try {
            $payload = json_encode(['v' => 1, 'timezone' => $preference->timezone->value(), 'source' => $preference->source->value, 'recorded_at' => $preference->recordedAt->format(\DateTimeInterface::ATOM)], JSON_THROW_ON_ERROR);
            $payload64 = $this->base64UrlEncode($payload);
            $value = $payload64.'.'.$this->base64UrlEncode(hash_hmac('sha256', $payload64, $this->key, true));
            if (strlen($value) > $this->maxEncodedSize) {
                throw new \LengthException('Encoded timezone cookie exceeds its size limit.');
            }
            $secure = $this->resolveSecure($request);
            $response->headers->setCookie(Cookie::create($this->name, $value, $this->clock->now()->getTimestamp() + $this->maxAge, $this->path, $this->domain, $secure, $this->httpOnly, false, $this->cookieSameSite()));
        } catch (\JsonException|\LengthException|\RuntimeException $failure) {
            throw TimezoneStorageException::operationFailed('write', $failure);
        }
    }

    public function clear(Request $request, Response $response): void
    {
        $response->headers->clearCookie($this->name, $this->path, $this->domain, $this->resolveSecure($request), $this->httpOnly, $this->cookieSameSite());
    }

    private function resolveSecure(Request $request): bool
    {
        $secure = $this->secure ?? $request->isSecure();
        if ((Cookie::SAMESITE_NONE === $this->sameSite || str_starts_with($this->name, '__Host-') || str_starts_with($this->name, '__Secure-')) && !$secure) {
            throw new TimezoneStorageException('The configured timezone cookie requires a secure request.');
        }
        return $secure;
    }

    /** @return Cookie::SAMESITE_LAX|Cookie::SAMESITE_STRICT|Cookie::SAMESITE_NONE */
    private function cookieSameSite(): string
    {
        return $this->sameSite;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function parseAtom(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);

        return false !== $date && $date->format(\DateTimeInterface::ATOM) === $value ? $date : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ('' === $value || 1 === preg_match('/[^A-Za-z0-9_-]/D', $value)) {
            return null;
        }
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        return false === $decoded ? null : $decoded;
    }
}
