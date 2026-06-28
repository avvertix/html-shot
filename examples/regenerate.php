<?php

/**
 * Regenerate every example image.
 *
 * Discovers each example script (examples/<name>/<name>.php) and runs it in its
 * own PHP process with shell_exec, so a single failing example can't abort the
 * rest of the batch. The helper (common.php) and the diagnostic (debug.php) at
 * the top level are skipped.
 *
 * Usage:
 *   php examples/regenerate.php
 */
$examplesDir = __DIR__;

// Collect runnable example scripts: one level deep, examples/<dir>/*.php.
$scripts = [];
foreach (glob($examplesDir.'/*', GLOB_ONLYDIR) as $dir) {
    foreach (glob($dir.'/*.php') as $script) {
        $scripts[] = $script;
    }
}
sort($scripts);

if ($scripts === []) {
    fwrite(STDERR, "No example scripts found under {$examplesDir}.\n");
    exit(1);
}

$php = escapeshellarg(PHP_BINARY);
$failures = [];
$saved = 0;
$start = microtime(true);

echo 'Regenerating '.count($scripts)." examples...\n\n";

foreach ($scripts as $script) {
    $rel = str_replace('\\', '/', substr($script, strlen($examplesDir) + 1));
    echo "==> {$rel}\n";

    $t0 = microtime(true);
    // Run the example and capture stdout + stderr together.
    $output = (string) shell_exec($php.' '.escapeshellarg($script).' 2>&1');
    $ms = (int) round((microtime(true) - $t0) * 1000);

    foreach (explode("\n", rtrim($output)) as $line) {
        if (trim($line) !== '') {
            echo "    {$line}\n";
        }
    }

    $saved += substr_count($output, 'Saved:');

    // Treat PHP-level errors as failures; warnings/notices are reported but pass.
    if (preg_match('/(Fatal error|Uncaught|Parse error)/i', $output)) {
        $failures[] = $rel;
        echo "    [FAIL] in {$ms}ms\n\n";
    } else {
        echo "    [ok] {$ms}ms\n\n";
    }
}

$elapsed = number_format(microtime(true) - $start, 1);
echo str_repeat('-', 48)."\n";
echo count($scripts)." scripts, {$saved} images, ".count($failures)." failed in {$elapsed}s\n";

if ($failures !== []) {
    echo 'Failed: '.implode(', ', $failures)."\n";
    exit(1);
}
