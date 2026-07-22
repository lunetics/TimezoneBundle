<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Distribution;

use PHPUnit\Framework\TestCase;

final class ExportPolicyTest extends TestCase
{
    public function testPublicFilesAreExportedAndDevelopmentFilesAreIgnored(): void
    {
        $attributesFile = dirname(__DIR__, 2).'/.gitattributes';
        $contents = file_get_contents($attributesFile);
        self::assertIsString($contents);

        $rules = array_values(array_filter(
            preg_split('/\R/', $contents) ?: [],
            static fn (string $line): bool => '' !== trim($line) && !str_starts_with(ltrim($line), '#'),
        ));

        foreach ([
            '/Resources/doc/scope.md export-ignore',
            '/Resources/doc/v2-implementation-plan.md export-ignore',
            '/LICENSE export-ignore',
        ] as $forbiddenRule) {
            self::assertNotContains($forbiddenRule, $rules, sprintf('%s must be included in distribution archives.', strtok($forbiddenRule, ' ')));
        }

        foreach ([
            '/.github export-ignore',
            '/.github/** export-ignore',
            '/.firecrawl export-ignore',
            '/.firecrawl/** export-ignore',
            '/.claude export-ignore',
            '/.claude/** export-ignore',
            '/Tests export-ignore',
            '/Tests/** export-ignore',
            '/.gitattributes export-ignore',
            '/.gitignore export-ignore',
            '/.travis.yml export-ignore',
            '/phpunit.xml.dist export-ignore',
            '/phpstan.neon.dist export-ignore',
            '/package.json export-ignore',
        ] as $requiredRule) {
            self::assertContains($requiredRule, $rules, sprintf('Missing distribution exclusion: %s.', $requiredRule));
        }
    }
}
