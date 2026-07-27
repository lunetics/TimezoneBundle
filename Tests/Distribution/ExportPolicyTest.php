<?php

declare(strict_types=1);

namespace Lunetics\TimezoneBundle\Tests\Distribution;

use PHPUnit\Framework\TestCase;

final class ExportPolicyTest extends TestCase
{
    private const ALLOWED_TOP_LEVEL = [
        'CHANGELOG.md',
        'composer.json',
        'LICENSE',
        'README.markdown',
        'Resources',
        'src',
        'UPGRADE-2.0.md',
    ];

    private const REQUIRED_PATHS = [
        'CHANGELOG.md',
        'composer.json',
        'LICENSE',
        'README.markdown',
        'Resources/config/routes.php',
        'Resources/doc/scope.md',
        'Resources/doc/v2-implementation-plan.md',
        'Resources/public/timezone.js',
        'UPGRADE-2.0.md',
        'src/LuneticsTimezoneBundle.php',
    ];

    public function testDistributionArchiveContainsOnlyAllowedPaths(): void
    {
        $root = dirname(__DIR__, 2);
        if (!is_dir($root.'/.git')) {
            self::markTestSkipped('Distribution policy requires a git checkout.');
        }
        $tar = shell_exec(sprintf('git -C %s archive --worktree-attributes HEAD', escapeshellarg($root)));
        if (!is_string($tar) || '' === $tar) {
            self::markTestSkipped('git archive is unavailable in this environment.');
        }

        $entries = self::tarEntries($tar);
        self::assertNotSame([], $entries, 'The distribution archive is empty.');

        foreach ($entries as $entry) {
            $topLevel = explode('/', rtrim($entry, '/'), 2)[0];
            self::assertContains($topLevel, self::ALLOWED_TOP_LEVEL, sprintf(
                'Unexpected path "%s" in the distribution archive; add an export-ignore rule to .gitattributes or extend the allowlist.',
                $entry,
            ));
        }

        foreach (self::REQUIRED_PATHS as $required) {
            self::assertContains($required, $entries, sprintf('Required distribution file "%s" is missing from the archive.', $required));
        }
    }

    /**
     * Minimal ustar reader: 512-byte headers, name at offset 0 (100 bytes),
     * octal size at 124 (12 bytes), optional name prefix at 345 (155 bytes).
     *
     * @return list<string>
     */
    private static function tarEntries(string $tar): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($tar);
        while ($offset + 512 <= $length) {
            $header = substr($tar, $offset, 512);
            $name = rtrim(substr($header, 0, 100), "\0");
            if ('' === $name) {
                break;
            }
            $prefix = rtrim(substr($header, 345, 155), "\0");
            $path = '' === $prefix ? $name : $prefix.'/'.$name;
            $typeFlag = substr($header, 156, 1);
            if (in_array($typeFlag, ['0', "\0"], true) && !str_ends_with($path, '/')) {
                $entries[] = $path;
            }
            $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
            $offset += 512 + (int) (ceil($size / 512) * 512);
        }

        return $entries;
    }
}
