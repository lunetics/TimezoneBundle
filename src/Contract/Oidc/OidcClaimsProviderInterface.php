<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Contract\Oidc;

use Symfony\Component\HttpFoundation\Request;

interface OidcClaimsProviderInterface
{
    /**
     * Returns the verified OIDC claims associated with the current request.
     *
     * Implementations return an empty array when the request has no OIDC
     * identity. Resolvers must treat the returned claims as sensitive data.
     *
     * @return array<string, mixed>
     */
    public function claimsForRequest(Request $request): array;
}
