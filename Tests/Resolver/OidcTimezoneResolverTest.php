<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Contract\Oidc\OidcClaimsProviderInterface;
use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolver\OidcTimezoneResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(OidcTimezoneResolver::class)]
final class OidcTimezoneResolverTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function absentClaims(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['zoneinfo' => null]];
        yield 'wrong type' => [['zoneinfo' => ['Europe/Berlin']]];
    }

    /** @param array<string, mixed> $claims */
    #[DataProvider('absentClaims')]
    public function testMissingOrNonStringClaimReturnsNull(array $claims): void
    {
        self::assertNull((new OidcTimezoneResolver($this->provider($claims)))->resolve(new Request()));
    }

    public function testValidConfigurableClaimProducesAuthenticatedResolution(): void
    {
        $resolution = (new OidcTimezoneResolver($this->provider(['tz' => 'Europe/Berlin']), 'tz'))->resolve(new Request());
        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('oidc_claim_11aebd1febd43cd1', $resolution->source);
        self::assertSame(ResolutionKind::AUTHENTICATED, $resolution->kind);
    }

    public function testDefaultZoneinfoClaimUsesDistinctSource(): void
    {
        $resolution = (new OidcTimezoneResolver($this->provider(['zoneinfo' => 'Europe/Berlin'])))->resolve(new Request());

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('oidc_zoneinfo', $resolution->source);
    }

    public function testInvalidClaimUsesValueObjectValidation(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        (new OidcTimezoneResolver($this->provider(['zoneinfo' => 'private-value'])))->resolve(new Request());
    }

    public function testProviderFailureIsWrappedWithoutClaimsInMessage(): void
    {
        $provider = new class implements OidcClaimsProviderInterface {
            public function claimsForRequest(Request $request): array { throw new \RuntimeException('zoneinfo=Secret/Claim'); }
        };

        try {
            (new OidcTimezoneResolver($provider))->resolve(new Request());
            self::fail('Expected resolver exception.');
        } catch (TimezoneResolverException $exception) {
            self::assertSame('The OIDC claims provider failed.', $exception->getMessage());
            self::assertStringNotContainsString('Secret', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $claims */
    private function provider(array $claims): OidcClaimsProviderInterface
    {
        return new class($claims) implements OidcClaimsProviderInterface {
            /** @param array<string, mixed> $claims */
            public function __construct(private readonly array $claims) {}
            public function claimsForRequest(Request $request): array { return $this->claims; }
        };
    }
}
