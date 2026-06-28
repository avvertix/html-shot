<?php

declare(strict_types=1);

namespace HtmlShot\Tests\Unit;

use Closure;
use FilesystemIterator;
use HtmlShot\Console\NativeLibraryInstaller;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Unit tests for the native library installer covering the install and update
 * flows. Network access is stubbed via an injected FakeHttp client so no real
 * HTTP calls are made; filesystem effects use throwaway temp directories.
 */
final class NativeLibraryInstallerTest extends TestCase
{
    private const TAG_URL = 'https://api.github.com/repos/avvertix/html-shot/releases/tags/';

    private const DOWNLOAD_BASE = 'https://dl.example/';

    private string $packageRoot;

    private string $projectRoot;

    private FakeHttp $http;

    protected function setUp(): void
    {
        $this->packageRoot = $this->makeTempDir('htmlshot_pkg_');
        $this->projectRoot = $this->makeTempDir('htmlshot_proj_');
        $this->http = new FakeHttp;
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->packageRoot);
        $this->removeDir($this->projectRoot);
    }

    public function test_install_downloads_platform_asset_and_writes_lock(): void
    {
        $assets = $this->platformAssets();
        $this->stubRelease('v0.1.0', $assets);

        $this->installer()->install($this->io(), force: false, version: 'v0.1.0');

        $name = $this->expectedAssetName();
        $libFile = $this->packageRoot.'/lib/'.$name;
        self::assertFileExists($libFile);
        self::assertSame($assets[$name], file_get_contents($libFile));

        $package = $this->readLockedPackage();
        self::assertSame(NativeLibraryInstaller::PACKAGE_NAME, $package['name']);
        self::assertSame('v0.1.0', $package['version']);
        // The full asset set is captured (every platform), ordered for stable diffs.
        self::assertSame(
            ['libtakumi_php.dylib', 'libtakumi_php.so', 'takumi_php.dll'],
            array_keys($package['assets']),
        );
        self::assertArrayHasKey('installed-at', $package);
    }

    public function test_install_reuses_lock_without_contacting_the_api(): void
    {
        $assets = $this->platformAssets();
        $this->writeLockFile('v0.1.0', $assets);

        // Only the download URLs are stubbed — deliberately no API tag endpoint.
        $this->stubDownloads('v0.1.0', $assets);

        $this->installer()->install($this->io(), force: false, version: null);

        self::assertFileExists($this->packageRoot.'/lib/'.$this->expectedAssetName());

        foreach ($this->http->requested as $url) {
            self::assertStringNotContainsString('api.github.com', $url, 'lock reuse must not hit the API');
        }
    }

    public function test_install_skips_download_when_file_present_and_verified(): void
    {
        $assets = $this->platformAssets();
        $this->writeLockFile('v0.1.0', $assets);

        $name = $this->expectedAssetName();
        mkdir($this->packageRoot.'/lib');
        file_put_contents($this->packageRoot.'/lib/'.$name, $assets[$name]);

        $this->installer()->install($this->io(), force: false, version: null);

        self::assertSame([], $this->http->requested, 'no HTTP calls when the file is already present and verified');
    }

    public function test_install_augments_lock_missing_the_current_platform_asset(): void
    {
        $assets = $this->platformAssets();
        $name = $this->expectedAssetName();

        // Lock generated on another OS: it has an asset, but not this platform's.
        $otherName = $name === 'takumi_php.dll' ? 'libtakumi_php.so' : 'takumi_php.dll';
        $this->writeLockFile('v0.1.0', [$otherName => $assets[$otherName]]);

        $this->stubRelease('v0.1.0', $assets);

        $this->installer()->install($this->io(), force: false, version: null);

        self::assertFileExists($this->packageRoot.'/lib/'.$name);
        self::assertContains(self::TAG_URL.'v0.1.0', $this->http->requested, 'missing platform asset triggers an API fetch');

        // The lock is healed to carry every platform's asset.
        self::assertSame(
            ['libtakumi_php.dylib', 'libtakumi_php.so', 'takumi_php.dll'],
            array_keys($this->readLockedPackage()['assets']),
        );
    }

    public function test_update_overwrites_existing_file_and_bumps_locked_version(): void
    {
        $oldAssets = $this->platformAssets();
        $name = $this->expectedAssetName();

        // Pre-existing install pinned to v0.1.0.
        $this->writeLockFile('v0.1.0', $oldAssets);
        mkdir($this->packageRoot.'/lib');
        file_put_contents($this->packageRoot.'/lib/'.$name, $oldAssets[$name]);

        // A newer release with different bytes.
        $newAssets = array_map(static fn (string $c): string => $c.'-v2', $oldAssets);
        $this->stubRelease('v0.2.0', $newAssets);

        // UpdateCommand drives install() with force: true.
        $this->installer()->install($this->io(), force: true, version: 'v0.2.0');

        self::assertSame(
            $newAssets[$name],
            file_get_contents($this->packageRoot.'/lib/'.$name),
            'update must overwrite the existing binary',
        );
        self::assertSame('v0.2.0', $this->readLockedPackage()['version']);
    }

    public function test_install_rejects_a_checksum_mismatch_and_writes_nothing(): void
    {
        $assets = $this->platformAssets();

        // Release advertises the correct digests…
        $this->http->responses[self::TAG_URL.'v0.1.0'] = [
            'status' => 200,
            'body' => $this->releaseJson('v0.1.0', $assets),
        ];
        // …but every download serves tampered bytes.
        foreach ($assets as $assetName => $content) {
            $this->http->responses[self::DOWNLOAD_BASE.'v0.1.0/'.$assetName] = ['status' => 200, 'body' => 'TAMPERED'];
        }

        try {
            $this->installer()->install($this->io(), force: false, version: 'v0.1.0');
            self::fail('Expected a checksum mismatch to throw.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Checksum mismatch', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->packageRoot.'/lib/'.$this->expectedAssetName());
        self::assertFileDoesNotExist($this->projectRoot.'/natives.lock');
    }

    public function test_install_preserves_other_packages_in_the_lock(): void
    {
        $assets = $this->platformAssets();

        // Seed a lock owned by an unrelated native package.
        file_put_contents($this->projectRoot.'/natives.lock', json_encode([
            'packages' => [[
                'name' => 'acme/other-native',
                'version' => '2.0.0',
                'assets' => ['libacme.so' => ['url' => 'https://x/libacme.so', 'digest' => 'sha256:00']],
                'installed-at' => '2026-01-01T00:00:00Z',
            ]],
        ]));

        $this->stubRelease('v0.1.0', $assets);
        $this->installer()->install($this->io(), force: false, version: 'v0.1.0');

        $lock = json_decode((string) file_get_contents($this->projectRoot.'/natives.lock'), true);
        $names = array_column($lock['packages'], 'name');
        self::assertContains('acme/other-native', $names);
        self::assertContains(NativeLibraryInstaller::PACKAGE_NAME, $names);
    }

    private function installer(): NativeLibraryInstaller
    {
        return new NativeLibraryInstaller(
            $this->packageRoot,
            $this->projectRoot,
            Closure::fromCallable($this->http),
        );
    }

    /**
     * The three platform binaries with arbitrary (but distinct) fake contents.
     *
     * @return array<string, string>
     */
    private function platformAssets(): array
    {
        return [
            'libtakumi_php.so' => 'ELF-FAKE-SO',
            'libtakumi_php.dylib' => 'MACHO-FAKE-DYLIB',
            'takumi_php.dll' => 'PE-FAKE-DLL',
        ];
    }

    /**
     * The asset filename the installer should pick on the host running the test.
     */
    private function expectedAssetName(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'takumi_php.dll',
            'Darwin' => 'libtakumi_php.dylib',
            default => 'libtakumi_php.so',
        };
    }

    /**
     * @param  array<string, string>  $assets  name => file contents
     */
    private function releaseJson(string $tag, array $assets): string
    {
        $list = [];
        foreach ($assets as $name => $content) {
            $list[] = [
                'name' => $name,
                'browser_download_url' => self::DOWNLOAD_BASE.$tag.'/'.$name,
                'digest' => 'sha256:'.hash('sha256', $content),
            ];
        }

        return (string) json_encode(['tag_name' => $tag, 'assets' => $list]);
    }

    /**
     * Stub the release API endpoint and every asset download for a tag.
     *
     * @param  array<string, string>  $assets
     */
    private function stubRelease(string $tag, array $assets): void
    {
        $this->http->responses[self::TAG_URL.$tag] = ['status' => 200, 'body' => $this->releaseJson($tag, $assets)];
        $this->stubDownloads($tag, $assets);
    }

    /**
     * @param  array<string, string>  $assets
     */
    private function stubDownloads(string $tag, array $assets): void
    {
        foreach ($assets as $name => $content) {
            $this->http->responses[self::DOWNLOAD_BASE.$tag.'/'.$name] = ['status' => 200, 'body' => $content];
        }
    }

    /**
     * Write a natives.lock for this package with the given assets.
     *
     * @param  array<string, string>  $assets
     */
    private function writeLockFile(string $tag, array $assets): void
    {
        $assetMap = [];
        foreach ($assets as $name => $content) {
            $assetMap[$name] = [
                'url' => self::DOWNLOAD_BASE.$tag.'/'.$name,
                'digest' => 'sha256:'.hash('sha256', $content),
            ];
        }

        file_put_contents($this->projectRoot.'/natives.lock', json_encode([
            'packages' => [[
                'name' => NativeLibraryInstaller::PACKAGE_NAME,
                'version' => $tag,
                'assets' => $assetMap,
                'installed-at' => '2026-01-01T00:00:00Z',
            ]],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function readLockedPackage(): array
    {
        $lock = json_decode((string) file_get_contents($this->projectRoot.'/natives.lock'), true);

        foreach ($lock['packages'] as $package) {
            if ($package['name'] === NativeLibraryInstaller::PACKAGE_NAME) {
                return $package;
            }
        }

        self::fail('No locked entry for '.NativeLibraryInstaller::PACKAGE_NAME);
    }

    private function io(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new BufferedOutput);
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(6));
        mkdir($dir, 0o777, true);

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        /** @var iterable<\SplFileInfo> $items */
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}

/**
 * Callable HTTP stub: serves canned responses keyed by URL and records every URL
 * requested, so tests can assert on network behaviour offline.
 */
final class FakeHttp
{
    /** @var array<string, array{status: int, body: string|false}> */
    public array $responses = [];

    /** @var list<string> */
    public array $requested = [];

    /**
     * @param  list<string>  $headers
     * @return array{status: int, body: string|false}
     */
    public function __invoke(string $url, array $headers, int $timeout): array
    {
        $this->requested[] = $url;

        return $this->responses[$url] ?? ['status' => 404, 'body' => false];
    }
}
