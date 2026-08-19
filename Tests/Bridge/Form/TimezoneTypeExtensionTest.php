<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\Form;

use Lunetics\TimezoneBundle\Bridge\Form\TimezoneTypeExtension;
use Lunetics\TimezoneBundle\Context\CurrentTimezoneProviderInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Forms;

final class TimezoneTypeExtensionTest extends TestCase
{
    public function testItExtendsOnlyCompatibleTypes(): void
    {
        self::assertSame(
            [DateTimeType::class, DateType::class, TimeType::class],
            iterator_to_array(TimezoneTypeExtension::getExtendedTypes()),
        );
    }

    /** @param class-string<FormTypeInterface<null>> $type */
    #[DataProvider('typeProvider')]
    public function testLazyDefaultAndExplicitOverrideWithoutModelTimezone(string $type): void
    {
        $provider = $this->createStub(CurrentTimezoneProviderInterface::class);
        $provider->method('getTimezone')->willReturn(TimezoneId::fromString('Europe/Berlin'));
        $extension = new TimezoneTypeExtension($provider);
        $factory = Forms::createFormFactoryBuilder()->addTypeExtension($extension)->getFormFactory();

        $defaults = $factory->create($type)->getConfig()->getOptions();
        self::assertSame('Europe/Berlin', $defaults['view_timezone']);
        self::assertArrayHasKey('model_timezone', $defaults);

        $options = ['view_timezone' => 'Asia/Tokyo', 'model_timezone' => 'UTC'];
        if (TimeType::class === $type) {
            $options['reference_date'] = new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC'));
        }
        $explicit = $factory->create($type, null, $options)->getConfig()->getOptions();
        self::assertSame('Asia/Tokyo', $explicit['view_timezone']);
        self::assertSame('UTC', $explicit['model_timezone']);
    }

    /** @param class-string<FormTypeInterface<null>> $type */
    #[DataProvider('typeProvider')]
    public function testConfiguredModelTimezoneWinsWithoutAReferenceDate(string $type): void
    {
        $provider = $this->createStub(CurrentTimezoneProviderInterface::class);
        $provider->method('getTimezone')->willReturn(TimezoneId::fromString('Europe/Berlin'));
        $factory = Forms::createFormFactoryBuilder()->addTypeExtension(new TimezoneTypeExtension($provider))->getFormFactory();

        $options = $factory->create($type, null, ['model_timezone' => 'UTC'])->getConfig()->getOptions();

        self::assertSame('UTC', $options['view_timezone'], 'Without a reference date the form must keep its model timezone instead of failing to build.');
        self::assertSame('UTC', $options['model_timezone']);
    }

    /** @param class-string<FormTypeInterface<null>> $type */
    #[DataProvider('typeProvider')]
    public function testCurrentTimezoneStillAppliesWhenAReferenceDateResolvesTheOffset(string $type): void
    {
        $provider = $this->createStub(CurrentTimezoneProviderInterface::class);
        $provider->method('getTimezone')->willReturn(TimezoneId::fromString('Europe/Berlin'));
        $factory = Forms::createFormFactoryBuilder()->addTypeExtension(new TimezoneTypeExtension($provider))->getFormFactory();
        $options = ['model_timezone' => 'UTC'];
        // Only TimeType exposes reference_date; the other two types resolve the
        // offset from the date itself.
        if (TimeType::class === $type) {
            $options['reference_date'] = new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC'));
        }

        $resolved = $factory->create($type, null, $options)->getConfig()->getOptions();

        self::assertSame(TimeType::class === $type ? 'Europe/Berlin' : 'UTC', $resolved['view_timezone']);
    }

    /** @return iterable<string, array{class-string<FormTypeInterface<null>>}> */
    public static function typeProvider(): iterable
    {
        yield 'datetime' => [DateTimeType::class];
        yield 'date' => [DateType::class];
        yield 'time' => [TimeType::class];
    }
}
