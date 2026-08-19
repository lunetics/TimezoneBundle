<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Contract\User\TimezoneAwareUserInterface;
use Lunetics\TimezoneBundle\Contract\User\UserTimezoneAccessorInterface;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class UserTimezoneResolver implements TimezoneResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ?UserTimezoneAccessorInterface $accessor = null,
    ) {
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (null === $user) {
            return null;
        }

        if ($user instanceof TimezoneAwareUserInterface) {
            try {
                $timezone = $user->getTimezone();
            } catch (\Exception $exception) {
                throw TimezoneResolverException::userAccessorFailed($exception);
            }
        } elseif (null !== $this->accessor) {
            try {
                $timezone = $this->accessor->getTimezoneForUser($user);
            } catch (\Exception $exception) {
                throw TimezoneResolverException::userAccessorFailed($exception);
            }
        } else {
            return null;
        }

        if (null === $timezone) {
            return null;
        }

        return new TimezoneResolution(
            is_string($timezone) ? TimezoneId::fromString($timezone) : $timezone,
            'authenticated_user',
            ResolutionKind::AUTHENTICATED,
        );
    }
}
