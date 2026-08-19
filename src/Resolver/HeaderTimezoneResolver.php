<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

final readonly class HeaderTimezoneResolver implements TimezoneResolverInterface
{
    /** @param list<string> $trustedSources */
    public function __construct(
        private string $headerName = 'X-Timezone',
        private HeaderTrustMode $trustMode = HeaderTrustMode::FRAMEWORK,
        private array $trustedSources = [],
        private int $maximumLength = 255,
    ) {
        if ('' === $headerName) {
            throw new \InvalidArgumentException('The timezone header name cannot be empty.');
        }

        if (HeaderTrustMode::ALLOWLIST === $trustMode && [] === $trustedSources) {
            throw new \InvalidArgumentException('Header allowlist trust requires at least one source address or CIDR.');
        }

        if ($maximumLength < 1 || $maximumLength > 4096) {
            throw new \InvalidArgumentException('The timezone header maximum length is outside the supported range.');
        }
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        if (!$this->isTrusted($request)) {
            return null;
        }

        $values = $request->headers->all($this->headerName);
        if ([] === $values) {
            return null;
        }

        if (1 !== count($values)) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        if (!is_string($values[0])) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        $value = trim($values[0]);
        if ('' === $value || strlen($value) > $this->maximumLength || str_contains($value, ',')) {
            throw InvalidTimezoneException::invalidIdentifier();
        }

        return new TimezoneResolution(
            TimezoneId::fromString($value),
            'trusted_header',
            ResolutionKind::EXPLICIT,
        );
    }

    private function isTrusted(Request $request): bool
    {
        return match ($this->trustMode) {
            HeaderTrustMode::FRAMEWORK => $request->isFromTrustedProxy(),
            HeaderTrustMode::ALLOWLIST => $this->isFromAllowlist($request),
            HeaderTrustMode::ANY => true,
        };
    }

    private function isFromAllowlist(Request $request): bool
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');

        return is_string($remoteAddress)
            && '' !== $remoteAddress
            && IpUtils::checkIp($remoteAddress, $this->trustedSources);
    }
}
