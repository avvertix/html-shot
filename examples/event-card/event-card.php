<?php

/**
 * Luma-style event invite card rendered with a custom stylesheet.
 *
 * Instead of inline styles, the layout is styled entirely from an external
 * CSS file (styles.css). The file is read at runtime and passed to the
 * renderer through the `stylesheets` option, so the markup stays clean and
 * only references semantic class names.
 *
 * Usage:
 *   php examples/event-card/event-card.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$logo = $assetsImages.'/luma.svg';
$photo = $assetsImages.'/martin-martz-W0NRebXbsjM-unsplash.jpg';

// The custom stylesheet lives next to this script.
$stylesheet = file_get_contents(__DIR__.'/styles.css');

$html = <<<HTML
<div class="card">
    <div class="left">
        <img class="logo" src="{$logo}" alt="luma" />
        <div class="body">
            <h1 class="title">Bloom &amp; Breathe: A Mindful Floral Session</h1>
            <div class="rsvp">RSVP</div>
        </div>
    </div>
    <div class="right">
        <img class="photo" src="{$photo}" alt="Event cover" />
    </div>
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => 1200,
    'height' => 630,
    'format' => 'png',
    // Pass any number of custom stylesheets here; they are applied on top of
    // the document just like a linked CSS file would be in a browser.
    'stylesheets' => [$stylesheet],
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ],
]);

save_to_output($png, 'event-card.png', $outputDir);
