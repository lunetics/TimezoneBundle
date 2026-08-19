<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\MaxMind;

use Lunetics\TimezoneBundle\Bridge\MaxMind\MaxMindDatabaseCheckCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MaxMindDatabaseCheckCommandTest extends TestCase
{
    public function testAcceptsCityMetadataWithoutPrintingThePath(): void
    {
        $path = $this->readableFile();
        try {
            $tester = new CommandTester(new MaxMindDatabaseCheckCommand($path, static fn (): object => (object) ['databaseType' => 'GeoLite2-City']));
            self::assertSame(0, $tester->execute([]));
            self::assertStringNotContainsString($path, $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testRejectsNonCityMetadata(): void
    {
        $path = $this->readableFile();
        try {
            $tester = new CommandTester(new MaxMindDatabaseCheckCommand($path, static fn (): object => (object) ['databaseType' => 'GeoLite2-Country']));
            self::assertNotSame(0, $tester->execute([]));
            self::assertStringContainsString('not a City database', $tester->getDisplay());
        } finally {
            unlink($path);
        }
    }

    public function testUnreadableDatabaseFailsBeforeFactoryIsCalled(): void
    {
        $called = false;
        $path = sys_get_temp_dir().'/missing-timezone-'.bin2hex(random_bytes(6)).'.mmdb';
        $tester = new CommandTester(new MaxMindDatabaseCheckCommand($path, static function () use (&$called): object {
            $called = true;
            return (object) ['databaseType' => 'GeoIP2-City'];
        }));

        self::assertNotSame(0, $tester->execute([]));
        self::assertFalse($called);
        self::assertStringNotContainsString($path, $tester->getDisplay());
    }

    private function readableFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'timezone-mmdb-');
        self::assertIsString($path);

        return $path;
    }
}
