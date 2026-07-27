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
        if (!file_exists($root.'/.git')) {
            self::markTestSkipped('Distribution policy requires a git checkout.');
        }
        $pipes = [];
        $process = proc_open(
            ['git', '-C', $root, 'archive', 'HEAD'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process, 'Unable to start git archive.');
        $tar = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        self::assertIsString($tar);
        self::assertSame(0, $exitCode, sprintf('git archive failed (exit %d): %s', $exitCode, is_string($stderr) ? trim($stderr) : ''));

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
     * Minimal ustar/PAX reader: 512-byte headers, name at offset 0 (100
     * bytes), octal size at 124 (12 bytes), type flag at 156, name prefix at
     * 345 (155 bytes). PAX extended headers ('x') may override the following
     * entry's path; GNU long-name entries ('L') carry it as data. Unsupported
     * entry types fail the test instead of being silently dropped.
     *
     * @return list<string>
     */
    private static function tarEntries(string $tar): array
    {
        $entries = [];
        $offset = 0;
        $length = strlen($tar);
        $pendingPath = null;
        while ($offset + 512 <= $length) {
            $header = substr($tar, $offset, 512);
            $name = rtrim(substr($header, 0, 100), "\0");
            if ('' === $name) {
                break;
            }
            $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
            $data = substr($tar, $offset + 512, $size);
            $offset += 512 + (int) (ceil($size / 512) * 512);
            $typeFlag = substr($header, 156, 1);
            if ('g' === $typeFlag) {
                continue;
            }
            if ('x' === $typeFlag) {
                $pendingPath = self::paxPath($data);
                continue;
            }
            if ('L' === $typeFlag) {
                $pendingPath = rtrim($data, "\0");
                continue;
            }
            $prefix = rtrim(substr($header, 345, 155), "\0");
            $path = $pendingPath ?? ('' === $prefix ? $name : $prefix.'/'.$name);
            $pendingPath = null;
            if ('5' === $typeFlag || str_ends_with($path, '/')) {
                continue;
            }
            self::assertContains($typeFlag, ['0', "\0", '2'], sprintf(
                'Unsupported tar entry type "%s" for "%s" — extend the parser before trusting this gate.',
                addslashes($typeFlag),
                $path,
            ));
            $entries[] = $path;
        }

        return $entries;
    }

    /**
     * PAX records have the form "<decimal length> <keyword>=<value>\n" where
     * the length covers the complete record including itself.
     */
    private static function paxPath(string $data): ?string
    {
        $offset = 0;
        $path = null;
        $length = strlen($data);
        while ($offset < $length) {
            $space = strpos($data, ' ', $offset);
            if (false === $space) {
                break;
            }
            $recordLength = (int) substr($data, $offset, $space - $offset);
            if ($recordLength <= 0) {
                break;
            }
            $record = substr($data, $space + 1, $offset + $recordLength - $space - 2);
            $offset += $recordLength;
            if (str_starts_with($record, 'path=')) {
                $path = substr($record, 5);
            }
        }

        return $path;
    }
}
