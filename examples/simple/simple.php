<?php

/**
 * Render a simple text in the middle of the canvas
 *
 * Usage:
 *   php examples/simple/simple.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$html = '<div style="display:flex; justify-content: center; align-items: center; width:100%; height:100%; font-family:Geist; font-size:100px; font-weight:900; color: #000; background: #ecfeff">That was fast!</div>';

$png = HtmlShot::render($html, [
    'width' => 1280,
    'height' => 630,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ]
]);

save_to_output($png, 'simple.png', $outputDir);
