<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Resolver\CallableTimezoneResolver;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CallableTimezoneResolver::class)]
final class CallableTimezoneResolverTest extends TestCase
{
    public function testWrapsStringsAndValueObjectsWithConfiguredMetadata(): void
    {
        $request = Request::create('/');
        $stringResolver = new CallableTimezoneResolver(
            static fn (Request $_request): string => 'Europe/Berlin',
            'tenant_callable',
            ResolutionKind::AUTHENTICATED,
        );
        $valueResolver = new CallableTimezoneResolver(
            static fn (Request $_request): TimezoneId => TimezoneId::fromString('UTC'),
            'fallback_callable',
        );

        $stringResolution = $stringResolver->resolve($request);
        $valueResolution = $valueResolver->resolve($request);

        self::assertNotNull($stringResolution);
        self::assertSame('Europe/Berlin', $stringResolution->timezone->value());
        self::assertSame('tenant_callable', $stringResolution->source);
        self::assertSame(ResolutionKind::AUTHENTICATED, $stringResolution->kind);
        self::assertSame('UTC', $valueResolution?->timezone->value());
    }

    public function testPassesThroughACompleteResolutionAndNull(): void
    {
        $expected = new TimezoneResolution(
            TimezoneId::fromString('UTC'),
            'complete',
            ResolutionKind::EXPLICIT,
        );
        $request = Request::create('/');

        self::assertSame(
            $expected,
            (new CallableTimezoneResolver(
                static fn (Request $_request): TimezoneResolution => $expected,
                'ignored_for_complete_result',
            ))->resolve($request),
        );
        self::assertNull((new CallableTimezoneResolver(
            static fn (Request $_request): ?string => null,
            'empty_callable',
        ))->resolve($request));
    }

    public function testInvalidStringsUseTheSingleTimezoneValidationPath(): void
    {
        $resolver = new CallableTimezoneResolver(
            static fn (Request $_request): string => 'invalid',
            'invalid_callable',
        );

        $this->expectException(InvalidTimezoneException::class);
        $resolver->resolve(Request::create('/'));
    }

    public function testRejectsUnsupportedReturnTypesAsDeclaredResolverFailures(): void
    {
        $resolver = new CallableTimezoneResolver(static fn (Request $_request): int => 42, 'invalid_callable');

        $this->expectException(TimezoneResolverException::class);
        $resolver->resolve(Request::create('/'));
    }

    public function testDoesNotHideProgrammerErrorsFromTheCallable(): void
    {
        $resolver = new CallableTimezoneResolver(static function (Request $_request): never {
            throw new \LogicException('Broken application callback.');
        }, 'broken_callable');

        $this->expectException(\LogicException::class);
        $resolver->resolve(Request::create('/'));
    }
}
