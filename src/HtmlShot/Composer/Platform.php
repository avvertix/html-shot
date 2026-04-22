<?php

declare(strict_types=1);

namespace HtmlShot\Composer;

/**
 * Platform detection and matching for selecting the correct binary artifact.
 *
 * Adapted from codewithkyrian/platform-package-installer (MIT).
 */
class Platform
{
    public static function current(): array
    {
        $osName = strtolower(php_uname('s'));
        $arch = php_uname('m');

        return [
            'os' => $osName,
            'arch' => self::normalizeArchitecture($arch),
            'full' => php_uname(),
        ];
    }

    public static function normalizeArchitecture(string $arch): string
    {
        $arch = strtolower($arch);

        $archMap = [
            'x86_64' => 'x86_64',
            'amd64' => 'x86_64',
            'i386' => 'x86',
            'i686' => 'x86',
            'x64' => 'x86_64',
            'x86' => 'x86',
            '32' => 'x86',
            '64' => 'x86_64',
            'arm64' => 'arm64',
            'aarch64' => 'arm64',
            'armv7' => 'arm',
            'armv8' => 'arm64',
            'arm64v8' => 'arm64',
            'ppc64' => 'ppc64',
            'ppc64le' => 'ppc64le',
            's390x' => 's390x',
        ];

        return $archMap[$arch] ?? $arch;
    }

    public static function matches(string $definedPlatform, ?array $currentPlatform = null): bool
    {
        $currentPlatform ??= self::current();
        $definedPlatform = strtolower($definedPlatform);

        if ($definedPlatform === 'all') {
            return true;
        }

        $parts = explode('-', $definedPlatform);
        $os = $parts[0];
        $arch = count($parts) > 1 ? $parts[1] : null;

        $archMatch = $arch === null
            || self::normalizeArchitecture($arch) === $currentPlatform['arch'];

        return self::matchesOS($os, $currentPlatform['os']) && $archMatch;
    }

    /**
     * Return the value from $platformEntries whose key best matches the current platform,
     * preferring more-specific (os-arch) keys over os-only keys, or false if none match.
     *
     * @template T
     *
     * @param  array<string, T>  $platformEntries
     * @param  array{os: string, arch: string}|null  $currentPlatform
     * @return T|false
     */
    public static function findBestMatch(array $platformEntries, ?array $currentPlatform = null): mixed
    {
        $currentPlatform ??= self::current();

        $matches = array_filter(
            array_keys($platformEntries),
            fn ($p) => self::matches($p, $currentPlatform)
        );

        usort($matches, function (string $a, string $b): int {
            if ($a === 'all') {
                return 1;
            }
            if ($b === 'all') {
                return -1;
            }

            $aHasArch = str_contains($a, '-');
            $bHasArch = str_contains($b, '-');

            if ($aHasArch && ! $bHasArch) {
                return -1;
            }
            if (! $aHasArch && $bHasArch) {
                return 1;
            }

            return 0;
        });

        foreach ($matches as $platform) {
            return $platformEntries[$platform];
        }

        return false;
    }

    private static function matchesOS(string $definedOs, string $currentOs): bool
    {
        $osAliases = [
            'windows' => ['windows', 'win32', 'win64', 'windows nt'],
            'darwin' => ['macos', 'mac', 'darwin'],
            'linux' => ['linux', 'gnu/linux'],
            'raspberrypi' => ['raspbian', 'raspberry pi'],
        ];

        if ($definedOs === $currentOs) {
            return true;
        }

        foreach ($osAliases as $alias => $variations) {
            if ($definedOs === $alias && in_array($currentOs, $variations, true)) {
                return true;
            }
        }

        return false;
    }
}
