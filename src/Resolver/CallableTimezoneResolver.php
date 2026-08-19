<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Resolver;

use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use Symfony\Component\HttpFoundation\Request;

final readonly class CallableTimezoneResolver implements TimezoneResolverInterface
{
    private \Closure $resolver;

    /**
     * The callable may deliberately throw TimezoneResolverException for a
     * policy-controlled adapter failure. Other exceptions are programmer
     * errors and intentionally bubble through the chain.
     * The mixed return type is intentional because results are validated at runtime.
     *
     * @param callable(Request): mixed $resolver
     */
    public function __construct(
        callable $resolver,
        private string $source,
        private ResolutionKind $kind = ResolutionKind::INFERRED,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $source)) {
            throw new \InvalidArgumentException('The callable resolver source must be a safe, non-empty identifier.');
        }

        $this->resolver = $resolver(...);
    }

    public function resolve(Request $request): ?TimezoneResolution
    {
        $value = ($this->resolver)($request);

        if (null === $value || $value instanceof TimezoneResolution) {
            return $value;
        }

        if (is_string($value)) {
            $value = TimezoneId::fromString($value);
        }

        if (!$value instanceof TimezoneId) {
            throw TimezoneResolverException::invalidResultType();
        }

        return new TimezoneResolution($value, $this->source, $this->kind);
    }
}
