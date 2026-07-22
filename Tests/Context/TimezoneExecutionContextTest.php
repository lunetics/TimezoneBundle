<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Context;

use Lunetics\TimezoneBundle\Context\CurrentTimezoneProvider;
use Lunetics\TimezoneBundle\Context\TimezoneExecutionContext;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolution\TimezoneResolution;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TimezoneExecutionContextTest extends TestCase
{
    public function testNestingExceptionCleanupAndReset(): void
    {
        $context = new TimezoneExecutionContext();
        $berlin = TimezoneId::fromString('Europe/Berlin');
        $utc = TimezoneId::fromString('UTC');
        $context->run($berlin, function () use ($context, $berlin, $utc): void {
            $current = $context->current();
            self::assertNotNull($current);
            self::assertTrue($current->equals($berlin));
            $context->run($utc, function () use ($context, $utc): void {
                $current = $context->current();
                self::assertNotNull($current);
                self::assertTrue($current->equals($utc));
            });
            $current = $context->current();
            self::assertNotNull($current);
            self::assertTrue($current->equals($berlin));
        });
        self::assertNull($context->current());
        try {
            $context->run($utc, static fn () => throw new \RuntimeException('expected'));
        } catch (\RuntimeException) {
        }
        self::assertNull($context->current());
        $context->run($utc, function () use ($context): void { $context->reset(); });
        self::assertNull($context->current());
    }

    public function testProviderUsesExecutionContextThenMainRequestThenDefault(): void
    {
        $context = new TimezoneExecutionContext();
        $stack = new RequestStack();
        $default = TimezoneId::fromString('UTC');
        $provider = new CurrentTimezoneProvider($context, $stack, $default);
        self::assertSame(ResolutionKind::DEFAULT, $provider->getResolution()->kind);

        $main = new Request();
        $mainResolution = new TimezoneResolution(TimezoneId::fromString('Europe/Berlin'), 'test', ResolutionKind::INFERRED);
        $main->attributes->set(CurrentTimezoneProvider::RESOLUTION_ATTRIBUTE, $mainResolution);
        $stack->push($main);
        $stack->push(new Request());
        self::assertSame($mainResolution, $provider->getResolution());

        $context->run(TimezoneId::fromString('Asia/Tokyo'), function () use ($provider): void {
            self::assertSame('Asia/Tokyo', $provider->getTimezone()->value());
        });
        self::assertSame(ResolutionKind::DEFAULT, $provider->getResolutionForRequest(new Request())->kind);
    }
}
