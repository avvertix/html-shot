<?php

declare(strict_types=1);

namespace HtmlShot\Console;

use Composer\InstalledVersions;
use HtmlShot\Composer\Platform;
use OutOfRangeException;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Resolves the correct native library asset for the current platform from the
 * GitHub releases API and downloads it into the package `lib/` directory,
 * verifying the published checksum.
 *
 * A `natives.lock` file records, per native package, the resolved release and
 * the download URL and checksum of *every* platform asset for that release
 * It is written to the consuming project root (alongside composer.lock)
 * so it can be committed and shared: a lock generated on one OS lets a build on
 * a different OS install deterministically and verify the download against the
 * locked digest, without re-resolving from the API.
 */
final class NativeLibraryInstaller
{
    public const PACKAGE_NAME = 'avvertix/html-shot';

    public const LOCK_FILE = 'natives.lock';

    private const GITHUB_LATEST_URL = 'https://api.github.com/repos/avvertix/html-shot/releases/latest';

    private const GITHUB_VERSION_URL = 'https://api.github.com/repos/avvertix/html-shot/releases/tags/{version}';

    /**
     * @param  string  $packageRoot  The package directory (holds composer.json and lib/).
     * @param  string|null  $projectRoot  The consuming project root where natives.lock
     *                                    lives. Resolved from the working directory when null.
     * @param  (\Closure(string, list<string>, int): array{status: int, body: string|false})|null  $httpClient
     *                                                                                                          Network seam: GET a URL, returning status + body. Defaults to a
     *                                                                                                          stream wrapper; injectable so tests can stub HTTP access.
     */
    public function __construct(
        private readonly string $packageRoot,
        private readonly ?string $projectRoot = null,
        private readonly ?\Closure $httpClient = null,
    ) {}

    /**
     * Download the native library for the current platform.
     *
     * @param  bool  $force  Re-resolve and re-download, ignoring the lock file.
     * @param  string|null  $version  Override the release version to download.
     */
    public function install(SymfonyStyle $io, bool $force = false, ?string $version = null): void
    {
        $lock = $this->readLock();
        $entry = $this->packageEntry($lock);

        $artifact = $this->resolveArtifact($io, $entry, $force, $version);

        $libDir = $this->packageRoot.'/lib';

        if (! is_dir($libDir) && ! @mkdir($libDir, 0o755, true) && ! is_dir($libDir)) {
            throw new RuntimeException("Failed to create lib directory: {$libDir}");
        }

        $filename = $artifact['asset'];
        $destination = $libDir.'/'.$filename;

        // Reuse the file already on disk when it matches the expected checksum.
        if (! $force && is_file($destination) && $this->fileMatches($destination, $artifact['digest'])) {
            $io->writeln("Artifact <info>{$filename}</info> already present and verified, skipping download.");
            $this->writeLockIfChanged($entry, $artifact, $io);

            return;
        }

        $io->writeln("Downloading <info>{$filename}</info> ({$artifact['version']}) from <comment>{$artifact['url']}</comment>");

        $this->download($artifact['url'], $destination, $artifact['digest']);

        $this->writeLock($artifact);
        $io->writeln('Locked release in <info>'.self::LOCK_FILE.'</info>.');

        $io->success("Native library installed to lib/{$filename}");
    }

    /**
     * Decide which release/assets to install and which asset this platform needs.
     *
     * Without an explicit version or --force, a locked entry that already
     * carries this platform's asset is reused offline; one missing this
     * platform's asset is augmented from the API (keeping the locked version).
     * Otherwise the release is resolved fresh from the GitHub API.
     *
     * @param  array<string, mixed>|null  $entry  This package's locked entry.
     * @return array{version: string, assets: array<string, array{url: string, digest: ?string}>, asset: string, url: string, digest: ?string}
     */
    private function resolveArtifact(SymfonyStyle $io, ?array $entry, bool $force, ?string $version): array
    {
        if (! $force && $version === null && is_array($entry) && $this->lockHasVersion($entry)) {
            return $this->resolveFromLock($io, $entry);
        }

        $release = $this->resolveRelease($version);

        /** @var string $tag */
        $tag = $release['tag_name'] ?? '';
        $io->writeln("Resolved release <info>{$tag}</info>.");

        $assets = $this->assetsFromRelease($release);

        return $this->planFor($tag, $assets);
    }

    /**
     * Build an install plan from this package's locked entry, falling back to
     * the API only when it has no asset for the current platform.
     *
     * @param  array<string, mixed>  $entry
     * @return array{version: string, assets: array<string, array{url: string, digest: ?string}>, asset: string, url: string, digest: ?string}
     */
    private function resolveFromLock(SymfonyStyle $io, array $entry): array
    {
        $version = (string) $entry['version'];
        $assets = $this->lockAssets($entry);

        if ($this->findPlatformAsset($assets) !== null) {
            $io->writeln("Reusing locked release <info>{$version}</info> from ".self::LOCK_FILE.'.');

            return $this->planFor($version, $assets);
        }

        // The lock was generated on another platform (or predates the full-asset
        // format): fetch the locked release once and record every asset so this
        // platform — and every other — is captured going forward.
        $io->writeln(
            '<comment>'.self::LOCK_FILE." has no asset for this platform; resolving {$version} from GitHub.</comment>"
        );

        $release = $this->fetchReleaseByTag($version);
        $merged = $this->assetsFromRelease($release) + $assets;

        return $this->planFor((string) ($release['tag_name'] ?? $version), $merged);
    }

    /**
     * Combine the full asset map (persisted to the lock) with the single asset
     * selected for the current platform (downloaded now).
     *
     * @param  array<string, array{url: string, digest: ?string}>  $assets
     * @return array{version: string, assets: array<string, array{url: string, digest: ?string}>, asset: string, url: string, digest: ?string}
     */
    private function planFor(string $version, array $assets): array
    {
        $name = $this->selectAssetName($assets);

        return [
            'version' => $version,
            'assets' => $assets,
            'asset' => $name,
            'url' => $assets[$name]['url'],
            'digest' => $assets[$name]['digest'],
        ];
    }

    /**
     * Resolve the GitHub release to install: an explicit version if provided,
     * otherwise the installed package version (falling back to the latest
     * published release for source/dev installs).
     *
     * @return array<string, mixed>
     */
    private function resolveRelease(?string $version): array
    {
        if ($version !== null && $version !== '') {
            return $this->fetchReleaseByTag($version);
        }

        $installed = $this->installedVersion();

        // Source installs (local development, branch requirements) report an
        // alias such as "dev-main" which is not a published release; fall back
        // to whatever GitHub marks as the latest release.
        if (str_starts_with($installed, 'dev-')) {
            return $this->fetchLatestRelease();
        }

        return $this->fetchReleaseByTag($installed);
    }

    private function installedVersion(): string
    {
        if (! InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            // Running from the package's own checkout: fall back to the version
            // declared in composer.json, if any.
            return $this->versionFromComposerJson()
                ?? throw new RuntimeException(
                    'Unable to determine the package version. Pass one explicitly as the command argument.'
                );
        }

        $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);

        if ($version === null || $version === '') {
            throw new RuntimeException(
                'Composer reported no version for '.self::PACKAGE_NAME.
                '. Pass one explicitly as the command argument.'
            );
        }

        return $version;
    }

    /**
     * Fetch the latest published release from the GitHub API.
     *
     * @return array<string, mixed>
     */
    private function fetchLatestRelease(): array
    {
        return $this->fetchRelease(self::GITHUB_LATEST_URL)
            ?? throw new RuntimeException('No published release was found on GitHub.');
    }

    /**
     * Fetch a release by tag, tolerating the presence or absence of a leading
     * "v" so a Composer version such as "0.1.0" still matches a "v0.1.0" tag.
     *
     * @return array<string, mixed>
     */
    private function fetchReleaseByTag(string $version): array
    {
        foreach ($this->tagCandidates($version) as $tag) {
            $release = $this->fetchRelease(
                str_replace('{version}', rawurlencode($tag), self::GITHUB_VERSION_URL)
            );

            if ($release !== null) {
                return $release;
            }
        }

        throw new RuntimeException("No GitHub release found for version {$version}.");
    }

    /**
     * @return list<string>
     */
    private function tagCandidates(string $version): array
    {
        $candidates = [$version];

        $candidates[] = str_starts_with($version, 'v') ? substr($version, 1) : 'v'.$version;

        return array_values(array_unique($candidates));
    }

    /**
     * Extract the native library assets (url + digest, keyed by filename) from a
     * GitHub release payload. Non-library assets (checksums, source archives)
     * are ignored.
     *
     * @param  array<string, mixed>  $release
     * @return array<string, array{url: string, digest: ?string}>
     */
    private function assetsFromRelease(array $release): array
    {
        $assets = [];

        foreach ($release['assets'] ?? [] as $asset) {
            $name = $asset['name'] ?? null;
            $url = $asset['browser_download_url'] ?? null;

            if (! is_string($name) || ! is_string($url) || ! $this->isLibraryAsset($name)) {
                continue;
            }

            $assets[$name] = [
                'url' => $url,
                'digest' => isset($asset['digest']) && is_string($asset['digest']) ? $asset['digest'] : null,
            ];
        }

        return $assets;
    }

    private function isLibraryAsset(string $name): bool
    {
        return preg_match('/\.(so|dylib|dll)$/i', $name) === 1;
    }

    /**
     * Pick the asset filename for the current platform from an asset map.
     *
     * @param  array<string, array{url: string, digest: ?string}>  $assets
     */
    private function selectAssetName(array $assets): string
    {
        $name = $this->findPlatformAsset($assets);

        if ($name === null) {
            $platform = Platform::current();
            $available = $assets === [] ? 'none' : implode(', ', array_keys($assets));

            throw new OutOfRangeException(
                "No asset matches the current platform ({$platform['os']}-{$platform['arch']}). ".
                "Available: {$available}."
            );
        }

        return $name;
    }

    /**
     * @param  array<string, array{url: string, digest: ?string}>  $assets
     */
    private function findPlatformAsset(array $assets): ?string
    {
        // Honour the priority order of the candidates (e.g. prefer .dylib over
        // .so on macOS).
        foreach ($this->platformAssetNames() as $candidate) {
            if (isset($assets[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Candidate library filenames for the current OS, in order of preference.
     * Mirrors HtmlShot\TakumiFfi::resolveLibrary so the downloaded file is the
     * one the runtime will load.
     *
     * @return list<string>
     */
    private function platformAssetNames(): array
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => ['takumi_php.dll'],
            'Darwin' => ['libtakumi_php.dylib', 'libtakumi_php.so'],
            default => ['libtakumi_php.so'],
        };
    }

    private function versionFromComposerJson(): ?string
    {
        $path = $this->packageRoot.'/composer.json';

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $version = $decoded['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * Find this package's entry within the lock file. Supports the composer.lock
     * style `packages` list and the legacy single-package format (top-level
     * version/assets), which migrates to the list format on the next write.
     *
     * @param  array<string, mixed>|null  $lock
     * @return array<string, mixed>|null
     */
    private function packageEntry(?array $lock): ?array
    {
        if (! is_array($lock)) {
            return null;
        }

        if (isset($lock['packages']) && is_array($lock['packages'])) {
            foreach ($lock['packages'] as $package) {
                if (is_array($package) && ($package['name'] ?? null) === self::PACKAGE_NAME) {
                    return $package;
                }
            }

            return null;
        }

        // Legacy format: top-level version/assets describe this package alone.
        return isset($lock['version']) ? $lock : null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function lockHasVersion(array $entry): bool
    {
        return isset($entry['version']) && is_string($entry['version']) && $entry['version'] !== '';
    }

    /**
     * Normalise the asset map stored in a locked package entry.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, array{url: string, digest: ?string}>
     */
    private function lockAssets(array $entry): array
    {
        $assets = [];
        $entries = $entry['assets'] ?? null;

        if (! is_array($entries)) {
            return $assets;
        }

        foreach ($entries as $name => $asset) {
            if (! is_string($name) || ! is_array($asset) || ! isset($asset['url']) || ! is_string($asset['url'])) {
                continue;
            }

            $assets[$name] = [
                'url' => $asset['url'],
                'digest' => isset($asset['digest']) && is_string($asset['digest']) ? $asset['digest'] : null,
            ];
        }

        return $assets;
    }

    /**
     * Absolute path to the lock file in the consuming project root.
     */
    private function lockPath(): string
    {
        return $this->resolveProjectRoot().'/'.self::LOCK_FILE;
    }

    /**
     * Locate the project root where natives.lock should live. The command passes
     * its working directory (where the user invoked it), which is the consuming
     * project root; we fall back to the current working directory, then to the
     * package root for a standalone checkout.
     */
    private function resolveProjectRoot(): string
    {
        if ($this->projectRoot !== null && $this->projectRoot !== '') {
            return rtrim($this->projectRoot, '/\\');
        }

        $cwd = getcwd();

        return rtrim($cwd !== false ? $cwd : $this->packageRoot, '/\\');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readLock(): ?array
    {
        $path = $this->lockPath();

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Upsert this package's entry in the lock file, preserving any other
     * packages' entries (composer.lock style).
     *
     * @param  array{version: string, assets: array<string, array{url: string, digest: ?string}>, asset: string, url: string, digest: ?string}  $artifact
     */
    private function writeLock(array $artifact): void
    {
        $assets = $artifact['assets'];
        ksort($assets); // stable ordering for clean diffs across machines

        $entry = [
            'name' => self::PACKAGE_NAME,
            'version' => $artifact['version'],
            'assets' => $assets,
            'installed-at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $existing = $this->readLock();
        $packages = is_array($existing['packages'] ?? null) ? $existing['packages'] : [];

        // Replace this package's entry in place, or append it.
        $replaced = false;
        foreach ($packages as $i => $package) {
            if (is_array($package) && ($package['name'] ?? null) === self::PACKAGE_NAME) {
                $packages[$i] = $entry;
                $replaced = true;
                break;
            }
        }

        if (! $replaced) {
            $packages[] = $entry;
        }

        // Keep packages ordered by name for stable diffs.
        usort($packages, static fn (array $a, array $b): int => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        $payload = ['packages' => array_values($packages)];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (file_put_contents($this->lockPath(), $json.PHP_EOL) === false) {
            throw new RuntimeException('Failed to write '.self::LOCK_FILE.'.');
        }
    }

    /**
     * Persist the lock only when this package's release or asset set changed,
     * avoiding needless rewrites (and installed-at churn) on no-op installs.
     *
     * @param  array<string, mixed>|null  $entry  This package's current locked entry.
     * @param  array{version: string, assets: array<string, array{url: string, digest: ?string}>, asset: string, url: string, digest: ?string}  $artifact
     */
    private function writeLockIfChanged(?array $entry, array $artifact, SymfonyStyle $io): void
    {
        if (is_array($entry)
            && ($entry['version'] ?? null) === $artifact['version']
            && $this->lockAssets($entry) == $artifact['assets']
        ) {
            return;
        }

        $this->writeLock($artifact);
        $io->writeln('Locked release in <info>'.self::LOCK_FILE.'</info>.');
    }

    /**
     * Whether a file on disk matches the expected digest. With no digest there
     * is nothing to check, so an existing file is accepted as-is.
     */
    private function fileMatches(string $path, ?string $digest): bool
    {
        if ($digest === null || $digest === '') {
            return true;
        }

        [$algo, $expected] = array_pad(explode(':', $digest, 2), 2, '');

        if ($expected === '' || ! in_array($algo, hash_algos(), true)) {
            return true;
        }

        $actual = @hash_file($algo, $path);

        return is_string($actual) && hash_equals(strtolower($expected), strtolower($actual));
    }

    /**
     * GET a release from the GitHub API. Returns the decoded payload, or null
     * when the release does not exist (HTTP 404).
     *
     * @return array<string, mixed>|null
     */
    private function fetchRelease(string $url): ?array
    {
        $response = $this->httpGet($url, [
            'User-Agent: html-shot native installer',
            'Accept: application/vnd.github+json',
        ], 30);

        if ($response['status'] === 404) {
            return null;
        }

        if ($response['body'] === false || $response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("GitHub API request failed (HTTP {$response['status']}) for: {$url}");
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function download(string $url, string $destination, ?string $digest): void
    {
        $response = $this->httpGet($url, ['User-Agent: html-shot native installer'], 120);
        $content = $response['body'];

        if ($content === false || $response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException("Download failed for: {$url}");
        }

        if ($digest !== null && $digest !== '') {
            $this->verifyDigest($content, $digest);
        }

        if (file_put_contents($destination, $content) === false) {
            throw new RuntimeException("Failed to write artifact to: {$destination}");
        }
    }

    /**
     * Single network seam: GET a URL and return the final status code and body.
     * Delegates to the injected client when present so tests can stub HTTP.
     *
     * @param  list<string>  $headers
     * @return array{status: int, body: string|false}
     */
    private function httpGet(string $url, array $headers, int $timeout): array
    {
        if ($this->httpClient !== null) {
            return ($this->httpClient)($url, $headers, $timeout);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => $headers,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        // Pre-seed so it is defined even when no HTTP response is received
        // (e.g. a transport-level failure); file_get_contents overwrites it.
        $http_response_header = [];
        $body = @file_get_contents($url, false, $context);

        return ['status' => $this->statusCode($http_response_header), 'body' => $body];
    }

    /**
     * Extract the HTTP status code from the $http_response_header magic variable,
     * taking the last status line so redirects report the final response.
     *
     * @param  list<string>  $headers
     */
    private function statusCode(array $headers): int
    {
        $status = 0;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status;
    }

    /**
     * Verify downloaded bytes against a GitHub asset digest ("sha256:<hex>").
     */
    private function verifyDigest(string $content, string $digest): void
    {
        [$algo, $expected] = array_pad(explode(':', $digest, 2), 2, '');

        // Unknown or unsupported digest format: nothing to verify against.
        if ($expected === '' || ! in_array($algo, hash_algos(), true)) {
            return;
        }

        $actual = hash($algo, $content);

        if (! hash_equals(strtolower($expected), strtolower($actual))) {
            throw new RuntimeException(
                "Checksum mismatch for downloaded artifact: expected {$algo}:{$expected}, got {$algo}:{$actual}."
            );
        }
    }
}
