<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Timezone;

use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimezoneId::class)]
final class TimezoneIdTest extends TestCase
{
    public function testCreatesAValueFromEverySupportedBoundaryForm(): void
    {
        $timezone = TimezoneId::fromString('Europe/Berlin');

        self::assertSame('Europe/Berlin', $timezone->value());
        self::assertSame('Europe/Berlin', (string) $timezone);
        self::assertSame('Europe/Berlin', $timezone->toDateTimeZone()->getName());
        self::assertTrue($timezone->equals(TimezoneId::fromDateTimeZone(new \DateTimeZone('Europe/Berlin'))));

        $backwardCompatibleAliases = array_values(array_diff(
            \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
            \DateTimeZone::listIdentifiers(\DateTimeZone::ALL),
        ));

        if ([] !== $backwardCompatibleAliases) {
            $alias = $backwardCompatibleAliases[0];
            self::assertSame($alias, TimezoneId::fromString($alias)->value());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown' => ['Moon/Tranquility'];
        yield 'raw offset' => ['+02:00'];
        yield 'leading whitespace' => [' Europe/Berlin'];
        yield 'trailing whitespace' => ['Europe/Berlin '];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testRejectsUnsupportedIdentifiers(string $identifier): void
    {
        $this->expectException(InvalidTimezoneException::class);

        TimezoneId::fromString($identifier);
    }

    public function testNativeSerializationRevalidatesTheIdentifier(): void
    {
        $timezone = TimezoneId::fromString('Europe/Berlin');
        $unserialized = unserialize(serialize($timezone));

        self::assertInstanceOf(TimezoneId::class, $unserialized);
        self::assertTrue($timezone->equals($unserialized));

        $tampered = str_replace('Europe/Berlin', 'Invalid/Zonee', serialize($timezone));

        $this->expectException(InvalidTimezoneException::class);
        unserialize($tampered);
    }
}
