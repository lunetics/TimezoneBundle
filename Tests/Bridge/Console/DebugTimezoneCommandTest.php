<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Bridge\Console;

use Lunetics\TimezoneBundle\Bridge\Console\DebugTimezoneCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugTimezoneCommandTest extends TestCase
{
    public function testDisplaysPassedConfigurationInDeterministicOrder(): void
    {
        $tester = new CommandTester(new DebugTimezoneCommand('UTC', [
            ['name' => 'manual', 'priority' => 925],
            ['name' => 'browser', 'priority' => 800],
        ]));

        self::assertSame(0, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('Configured default: UTC', $display);
        self::assertLessThan(strpos($display, 'browser'), strpos($display, 'manual'));
        self::assertStringContainsString('925', $display);
        self::assertStringContainsString('800', $display);
    }
}
