<?php

declare(strict_types=1);

namespace HtmlShot\Composer;

use Composer\Script\Event;

class Installer
{
    private const PACKAGE_NAME = 'avvertix/html-shot';

    public static function downloadArtifact(Event $event): void
    {
        $composer = $event->getComposer();
        $io = $event->getIO();

        // Look up this package in the local repository so we read its own
        // composer.json rather than the root project's composer.json.
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $package = $localRepo->findPackage(self::PACKAGE_NAME, '*');

        if ($package !== null) {
            $packageRoot = $composer->getInstallationManager()->getInstallPath($package);
            $extra = $package->getExtra();
        } else {
            // Package is the root (development mode).
            $packageRoot = dirname(__DIR__, 3);
            $extra = $composer->getPackage()->getExtra();
        }

        $artifacts = $extra['artifacts'] ?? [];

        if ($artifacts === []) {
            $io->write('<warning>No artifacts configured in extra.artifacts — skipping binary download.</warning>');

            return;
        }

        $normalized = array_combine(
            array_map('strtolower', array_keys($artifacts)),
            array_values($artifacts)
        );

        $url = Platform::findBestMatch($normalized);

        if ($url === false) {
            $platform = Platform::current();
            $io->writeError(
                "<error>No artifact URL found for current platform ({$platform['os']}-{$platform['arch']}).</error>"
            );

            return;
        }

        $libDir = $packageRoot.'/lib';

        if (! is_dir($libDir) && ! mkdir($libDir, 0755, true)) {
            $io->writeError("<error>Failed to create lib directory: {$libDir}</error>");

            return;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        $filename = basename((string) $urlPath);

        if ($filename === '') {
            $io->writeError("<error>Cannot determine filename from artifact URL: {$url}</error>");

            return;
        }

        $destination = $libDir.'/'.$filename;

        if (file_exists($destination)) {
            $io->write("  - Artifact <info>{$filename}</info> already present, skipping download.");

            return;
        }

        $io->write("  - Downloading <info>{$filename}</info> from <comment>{$url}</comment>");

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => ['User-Agent: Composer HtmlShot-Installer/1.0'],
                'timeout' => 120,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            $io->writeError("<error>Download failed for: {$url}</error>");

            return;
        }

        if (file_put_contents($destination, $content) === false) {
            $io->writeError("<error>Failed to write artifact to: {$destination}</error>");

            return;
        }

        $io->write("  - Artifact saved to <info>lib/{$filename}</info>");
    }
}
