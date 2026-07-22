<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Resolver;

use Lunetics\TimezoneBundle\Resolver\StoredPreferenceTimezoneResolver;
use Lunetics\TimezoneBundle\Exception\TimezoneStorageException;
use Lunetics\TimezoneBundle\Resolution\PersistenceFailureStrategy;
use Lunetics\TimezoneBundle\Storage\PreferenceReadStatus;
use Lunetics\TimezoneBundle\Storage\PreferenceSource;
use Lunetics\TimezoneBundle\Storage\TimezonePreference;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceRead;
use Lunetics\TimezoneBundle\Storage\TimezonePreferenceStorageInterface;
use Lunetics\TimezoneBundle\Timezone\TimezoneId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class StoredPreferenceTimezoneResolverTest extends TestCase
{
    public function testFiltersSourceAndExposesReadStatus(): void
    {
        $storage = new class implements TimezonePreferenceStorageInterface {
            public function read(Request $request): TimezonePreferenceRead { return new TimezonePreferenceRead(PreferenceReadStatus::VALID, new TimezonePreference(TimezoneId::fromString('UTC'), PreferenceSource::BROWSER, new \DateTimeImmutable())); }
            public function write(Request $request, Response $response, TimezonePreference $preference): void { throw new \LogicException(); }
            public function clear(Request $request, Response $response): void { throw new \LogicException(); }
        };
        $request = new Request();
        self::assertNull((new StoredPreferenceTimezoneResolver($storage, PreferenceSource::MANUAL))->resolve($request));
        $cachedRead = $request->attributes->get(StoredPreferenceTimezoneResolver::READ_ATTRIBUTE);
        self::assertInstanceOf(TimezonePreferenceRead::class, $cachedRead);
        self::assertSame(PreferenceReadStatus::VALID, $cachedRead->status);
        self::assertSame('persisted_browser', (new StoredPreferenceTimezoneResolver($storage, PreferenceSource::BROWSER))->resolve($request)?->source);
    }

    public function testTwoSourceResolversReadStorageOnlyOncePerRequest(): void
    {
        $storage = new class implements TimezonePreferenceStorageInterface {
            public int $reads = 0;
            public function read(Request $request): TimezonePreferenceRead { ++$this->reads; return new TimezonePreferenceRead(PreferenceReadStatus::ABSENT); }
            public function write(Request $request, Response $response, TimezonePreference $preference): void {}
            public function clear(Request $request, Response $response): void {}
        };
        $request = new Request();
        (new StoredPreferenceTimezoneResolver($storage, PreferenceSource::MANUAL))->resolve($request);
        (new StoredPreferenceTimezoneResolver($storage, PreferenceSource::BROWSER))->resolve($request);
        self::assertSame(1, $storage->reads);
    }

    public function testPersistenceFailurePolicyIsAppliedInsideResolver(): void
    {
        $storage = new class implements TimezonePreferenceStorageInterface {
            public function read(Request $request): TimezonePreferenceRead { throw new TimezoneStorageException('unavailable'); }
            public function write(Request $request, Response $response, TimezonePreference $preference): void {}
            public function clear(Request $request, Response $response): void {}
        };
        self::assertNull((new StoredPreferenceTimezoneResolver($storage, null, PersistenceFailureStrategy::CONTINUE))->resolve(new Request()));

        $this->expectException(TimezoneStorageException::class);
        (new StoredPreferenceTimezoneResolver($storage, null, PersistenceFailureStrategy::THROW))->resolve(new Request());
    }
}
