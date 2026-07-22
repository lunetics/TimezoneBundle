<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Contract\User\TimezoneAwareUserInterface;
use Lunetics\TimezoneBundle\Contract\User\UserTimezoneAccessorInterface;
use Lunetics\TimezoneBundle\Exception\InvalidTimezoneException;
use Lunetics\TimezoneBundle\Exception\TimezoneResolverException;
use Lunetics\TimezoneBundle\Resolution\ResolutionKind;
use Lunetics\TimezoneBundle\Resolver\UserTimezoneResolver;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(UserTimezoneResolver::class)]
final class UserTimezoneResolverTest extends TestCase
{
    public function testAnonymousTokenStorageReturnsNull(): void
    {
        self::assertNull((new UserTimezoneResolver(new TokenStorage()))->resolve(new Request()));
    }

    public function testUsesTimezoneAwareUserBeforeExplicitAccessor(): void
    {
        $user = new TimezoneAwareTestUser('Europe/Berlin');
        $accessor = new class implements UserTimezoneAccessorInterface {
            public bool $called = false;
            public function getTimezoneForUser(object $user): string { $this->called = true; return 'UTC'; }
        };

        $resolution = (new UserTimezoneResolver($this->storageFor($user), $accessor))->resolve(new Request());

        self::assertNotNull($resolution);
        self::assertSame('Europe/Berlin', $resolution->timezone->value());
        self::assertSame('authenticated_user', $resolution->source);
        self::assertSame(ResolutionKind::AUTHENTICATED, $resolution->kind);
        self::assertFalse($accessor->called);
    }

    public function testUsesExplicitAccessorForAnOtherwiseUnsupportedUser(): void
    {
        $user = new PlainTestUser();
        $accessor = new class implements UserTimezoneAccessorInterface {
            public function getTimezoneForUser(object $user): TimezoneId { return TimezoneId::fromString('UTC'); }
        };

        self::assertSame('UTC', (new UserTimezoneResolver($this->storageFor($user), $accessor))->resolve(new Request())?->timezone->value());
        self::assertNull((new UserTimezoneResolver($this->storageFor($user)))->resolve(new Request()));
    }

    public function testInvalidUserTimezoneUsesValueObjectValidation(): void
    {
        $this->expectException(InvalidTimezoneException::class);
        (new UserTimezoneResolver($this->storageFor(new TimezoneAwareTestUser('not-a-timezone'))))->resolve(new Request());
    }

    public function testAccessorExceptionsAreWrappedButErrorsAreNotSwallowed(): void
    {
        $user = new PlainTestUser();
        $failing = new class implements UserTimezoneAccessorInterface {
            public function getTimezoneForUser(object $user): never { throw new \RuntimeException('secret'); }
        };

        try {
            (new UserTimezoneResolver($this->storageFor($user), $failing))->resolve(new Request());
            self::fail('Expected resolver exception.');
        } catch (TimezoneResolverException $exception) {
            self::assertSame('The user timezone accessor failed.', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }

        $broken = new class implements UserTimezoneAccessorInterface {
            public function getTimezoneForUser(object $user): never { throw new \TypeError('programmer error'); }
        };
        $this->expectException(\TypeError::class);
        (new UserTimezoneResolver($this->storageFor($user), $broken))->resolve(new Request());
    }

    public function testTimezoneAwareUserExceptionsAreWrappedWithoutLeakingMessage(): void
    {
        try {
            (new UserTimezoneResolver($this->storageFor(new FailingTimezoneAwareTestUser())))->resolve(new Request());
            self::fail('Expected resolver exception.');
        } catch (TimezoneResolverException $exception) {
            self::assertSame('The user timezone accessor failed.', $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            self::assertSame('secret timezone failure', $exception->getPrevious()->getMessage());
        }
    }

    public function testTimezoneAwareUserErrorsRemainUnwrapped(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('programmer error');
        (new UserTimezoneResolver($this->storageFor(new BrokenTimezoneAwareTestUser())))->resolve(new Request());
    }

    private function storageFor(UserInterface $user): TokenStorage
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'main'));
        return $storage;
    }
}

final class TimezoneAwareTestUser implements UserInterface, TimezoneAwareUserInterface
{
    public function __construct(private readonly TimezoneId|string|null $timezone) {}
    public function getTimezone(): TimezoneId|string|null { return $this->timezone; }
    public function getRoles(): array { return []; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return 'aware'; }
}

final class PlainTestUser implements UserInterface
{
    public function getRoles(): array { return []; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return 'plain'; }
}

final class FailingTimezoneAwareTestUser implements UserInterface, TimezoneAwareUserInterface
{
    public function getTimezone(): never { throw new \RuntimeException('secret timezone failure'); }
    public function getRoles(): array { return []; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return 'failing-aware'; }
}

final class BrokenTimezoneAwareTestUser implements UserInterface, TimezoneAwareUserInterface
{
    public function getTimezone(): never { throw new \TypeError('programmer error'); }
    public function getRoles(): array { return []; }
    public function eraseCredentials(): void {}
    public function getUserIdentifier(): string { return 'broken-aware'; }
}
