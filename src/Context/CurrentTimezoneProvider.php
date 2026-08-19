<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Context;

use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CurrentTimezoneProvider implements CurrentTimezoneProviderInterface
{
    public const RESOLUTION_ATTRIBUTE = '_lunetics_timezone.resolution';

    public function __construct(private TimezoneExecutionContextInterface $context, private RequestStack $requestStack, private TimezoneId $defaultTimezone)
    {
    }

    public function getResolution(): TimezoneResolution
    {
        if (null !== $timezone = $this->context->current()) {
            return new TimezoneResolution($timezone, 'execution_context', ResolutionKind::EXPLICIT);
        }
        $request = $this->requestStack->getMainRequest();
        return null === $request ? $this->defaultResolution() : $this->getResolutionForRequest($request);
    }

    public function getResolutionForRequest(Request $request): TimezoneResolution
    {
        $resolution = $request->attributes->get(self::RESOLUTION_ATTRIBUTE);
        return $resolution instanceof TimezoneResolution ? $resolution : $this->defaultResolution();
    }

    public function getTimezone(): TimezoneId
    {
        return $this->getResolution()->timezone;
    }

    public function getDateTimeZone(): \DateTimeZone
    {
        return $this->getTimezone()->toDateTimeZone();
    }

    private function defaultResolution(): TimezoneResolution
    {
        return new TimezoneResolution($this->defaultTimezone, 'default', ResolutionKind::DEFAULT);
    }
}
