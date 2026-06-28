<?php

/**
 * Render a simple text in the middle of the canvas
 *
 * Usage:
 *   php examples/tailwind/tailwind.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$html = <<<'HTML'
<div tw="flex h-full w-full flex-col justify-end bg-[#16130f] p-20">
    <div tw="flex flex-col">
        <h1 tw="m-0 text-8xl font-bold leading-none tracking-tighter text-white">HtmlShot.</h1>
        <h2 tw="m-0 mt-2 text-8xl font-bold leading-none tracking-tighter text-[#3b82f6]">html in, image out</h2>
        <p tw="mb-0 mt-10 text-3xl text-[#a8a29a]">HTML to image rendering for PHP, powered by Rust.</p>
    </div>
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => 1280,
    'height' => 630,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ]
]);

save_to_output($png, 'tailwind.png', $outputDir);
