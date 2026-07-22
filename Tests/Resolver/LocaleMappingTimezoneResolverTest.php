<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolver\LocaleMappingTimezoneResolver;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(LocaleMappingTimezoneResolver::class)]
final class LocaleMappingTimezoneResolverTest extends TestCase
{
    public function testResolvesAnExactOrBcp47SeparatedMapping(): void
    {
        $resolver = new LocaleMappingTimezoneResolver([
            'de_DE' => TimezoneId::fromString('Europe/Berlin'),
        ]);
        $request = Request::create('/');
        $request->setLocale('de-DE');

        $resolution = $resolver->resolve($request);

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('locale_mapping', $resolution->source);
        self::assertSame(ResolutionKind::INFERRED, $resolution->kind);
    }

    public function testReturnsNullForAnUnmappedLocale(): void
    {
        $request = Request::create('/');
        $request->setLocale('fr_FR');

        self::assertNull((new LocaleMappingTimezoneResolver([]))->resolve($request));
    }

    public function testHyphenatedMappingKeyMatchesUnderscoreRequestLocale(): void
    {
        $request = Request::create('/');
        $request->setLocale('de_DE');

        $resolution = (new LocaleMappingTimezoneResolver(['de-DE' => 'Europe/Berlin']))->resolve($request);

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
    }

    public function testRejectsKeysThatCollideAfterNormalization(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('collides after normalization');

        new LocaleMappingTimezoneResolver(['de-DE' => 'Europe/Berlin', 'de_DE' => 'Europe/Vienna']);
    }

    public function testRequiresNonEmptyLocaleKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LocaleMappingTimezoneResolver(['' => 'Europe/Berlin']);
    }
}
