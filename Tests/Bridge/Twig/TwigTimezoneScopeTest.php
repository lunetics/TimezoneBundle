<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\Twig;

use Lunetics\TimezoneBundle\Bridge\Twig\TwigTimezoneScope;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Twig\Extension\CoreExtension;

final class TwigTimezoneScopeTest extends TestCase
{
    public function testNestedScopesRestoreExactTimezoneAndReturnValue(): void
    {
        $core = new CoreExtension();
        $original = new \DateTimeZone('America/New_York');
        $core->setTimezone($original);
        $scope = new TwigTimezoneScope($core);

        $result = $scope->run(TimezoneId::fromString('Europe/Berlin'), function () use ($scope, $core): string {
            self::assertSame('Europe/Berlin', $core->getTimezone()->getName());
            $scope->run(TimezoneId::fromString('Asia/Tokyo'), static function () use ($core): void {
                self::assertSame('Asia/Tokyo', $core->getTimezone()->getName());
            });
            self::assertSame('Europe/Berlin', $core->getTimezone()->getName());

            return 'result';
        });

        self::assertSame('result', $result);
        self::assertSame($original, $core->getTimezone());
    }

    public function testExceptionAndResetRestoreOriginalTimezone(): void
    {
        $core = new CoreExtension();
        $original = new \DateTimeZone('UTC');
        $core->setTimezone($original);
        $scope = new TwigTimezoneScope($core);

        try {
            $scope->run(TimezoneId::fromString('Europe/Berlin'), static function (): void {
                throw new \RuntimeException('failure');
            });
            self::fail('Expected exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('failure', $exception->getMessage());
        }
        self::assertSame($original, $core->getTimezone());

        $scope->enter(TimezoneId::fromString('Europe/Berlin'));
        $scope->enter(TimezoneId::fromString('Asia/Tokyo'));
        $scope->reset();
        self::assertSame($original, $core->getTimezone());
        $scope->leave();
        self::assertSame($original, $core->getTimezone());
    }
}
