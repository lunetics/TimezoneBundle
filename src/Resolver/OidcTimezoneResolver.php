<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Contract\Oidc\OidcClaimsProviderInterface;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;

final readonly class OidcTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(
        private OidcClaimsProviderInterface $claimsProvider,
        private string $claim = 'zoneinfo',
    ) {
        if ('' === $claim) {
            throw new \InvalidArgumentException('The OIDC timezone claim name cannot be empty.');
        }
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        try {
            $claims = $this->claimsProvider->claimsForRequest($request);
        } catch (\Exception $exception) {
            throw TimezoneResolverException::oidcProviderFailed($exception);
        }

        $timezone = $claims[$this->claim] ?? null;
        if (!is_string($timezone)) {
            return null;
        }

        return new TimezoneResolution(
            TimezoneId::fromString($timezone),
            $this->traceSource(),
            ResolutionKind::AUTHENTICATED,
        );
    }

    private function traceSource(): string
    {
        if ('zoneinfo' === $this->claim) {
            return 'oidc_zoneinfo';
        }

        return 'oidc_claim_'.substr(hash('sha256', $this->claim), 0, 16);
    }
}
