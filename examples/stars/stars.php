<?php

/**
 * Render a GitHub repo stars counter
 *
 * Usage:
 *   php examples/stars/stars.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$star = $assetsImages.'/golden-star-disco-ball.png';

$html = <<<HTML
<div tw="flex h-full w-full flex-col items-center justify-end bg-taupe-800 p-20">
    <div tw="absolute h-full w-full text-center">
        <img src="{$star}" alt="" tw="h-full ">
    </div>
    <div tw="flex flex-col ">
        <h1 tw="text-shadow-lg m-0 text-[12em] font-bold leading-none tracking-tighter text-white">zero stars</h1>
    </div>
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => 1280,
    'height' => 630,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ],
]);

save_to_output($png, 'stars.png', $outputDir);
