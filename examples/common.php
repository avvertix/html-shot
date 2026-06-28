<?php

require __DIR__.'/../vendor/autoload.php';

$assetsImages = realpath(__DIR__.'/assets/images');

$fontsPath = realpath(__DIR__.'/../tests/fonts');

/**
 * Save PNG to file
 */
function save_to_output(mixed $png, string $filename, string $directory): void
{
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($directory.'/' . $filename, $png);

    echo "Saved: " . basename($directory). "/{$filename}\n";
}