<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Resolver\CountryTimezoneSourceInterface;
use Lunetics\TimezoneBundle\Resolver\LocaleTimezoneResolver;
use Lunetics\TimezoneBundle\Resolver\PhpCountryTimezoneSource;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(LocaleTimezoneResolver::class)]
#[CoversClass(PhpCountryTimezoneSource::class)]
final class LocaleTimezoneResolverTest extends TestCase
{
    public function testResolvesHyphenatedLocaleWithOrWithoutIntl(): void
    {
        $source = new RecordingCountryTimezoneSource([TimezoneId::fromString('Europe/Berlin')]);
        $request = Request::create('/');
        $request->setLocale('de-DE');

        $resolution = (new LocaleTimezoneResolver($source))->resolve($request);

        self::assertNotNull($resolution);
        self::assertSame('DE', $source->requestedCountry);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('locale_country', $resolution->source);
    }

    public function testReturnsNullForZeroOrMultipleCountryTimezones(): void
    {
        $request = Request::create('/');
        $request->setLocale('de_DE');

        self::assertNull((new LocaleTimezoneResolver(new RecordingCountryTimezoneSource([])))->resolve($request));
        self::assertNull((new LocaleTimezoneResolver(new RecordingCountryTimezoneSource([
            TimezoneId::fromString('Europe/Berlin'),
            TimezoneId::fromString('Europe/Paris'),
        ])))->resolve($request));
    }

    public function testReturnsNullWithoutQueryingTheSourceWhenLocaleHasNoCountry(): void
    {
        $source = new RecordingCountryTimezoneSource([TimezoneId::fromString('UTC')]);
        $request = Request::create('/');
        $request->setLocale('de');

        self::assertNull((new LocaleTimezoneResolver($source))->resolve($request));
        self::assertNull($source->requestedCountry);
    }

    public function testProductionSourceMirrorsPhpTzdataWithoutHardcodedTimezoneResults(): void
    {
        $expected = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'DE');
        $actual = array_map(
            static fn (TimezoneId $timezone): string => $timezone->value(),
            (new PhpCountryTimezoneSource())->forCountry('DE'),
        );

        self::assertSame($expected, $actual);
    }
}

final class RecordingCountryTimezoneSource implements CountryTimezoneSourceInterface
{
    public ?string $requestedCountry = null;

    /** @param list<TimezoneId> $timezones */
    public function __construct(private readonly array $timezones)
    {
    }

    public function forCountry(string $countryCode): array
    {
        $this->requestedCountry = $countryCode;

        return $this->timezones;
    }
}
