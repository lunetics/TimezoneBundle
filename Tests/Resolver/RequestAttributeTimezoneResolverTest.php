<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolver\RequestAttributeTimezoneResolver;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RequestAttributeTimezoneResolver::class)]
final class RequestAttributeTimezoneResolverTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function supportedValues(): iterable
    {
        yield 'string' => ['Europe/Berlin'];
        yield 'DateTimeZone' => [new \DateTimeZone('Europe/Berlin')];
        yield 'TimezoneId' => [TimezoneId::fromString('Europe/Berlin')];
    }

    #[DataProvider('supportedValues')]
    public function testResolvesSupportedAttributeValues(mixed $value): void
    {
        $request = Request::create('/');
        $request->attributes->set('_timezone', $value);

        $resolution = (new RequestAttributeTimezoneResolver())->resolve($request);

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('request_attribute', $resolution->source);
        self::assertSame(ResolutionKind::EXPLICIT, $resolution->kind);
    }

    public function testReturnsNullWhenTheAttributeIsMissingOrNull(): void
    {
        $request = Request::create('/');
        $resolver = new RequestAttributeTimezoneResolver('tenant_timezone');

        self::assertNull($resolver->resolve($request));

        $request->attributes->set('tenant_timezone', null);
        self::assertNull($resolver->resolve($request));
    }

    public function testRejectsUnsupportedAttributeTypesWithoutCoercion(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_timezone', 123);

        $this->expectException(InvalidTimezoneException::class);
        (new RequestAttributeTimezoneResolver())->resolve($request);
    }

    public function testRejectsInvalidTimezoneStringsThroughTheValueObject(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_timezone', 'Not/A_Timezone');

        $this->expectException(InvalidTimezoneException::class);
        (new RequestAttributeTimezoneResolver())->resolve($request);
    }
}
