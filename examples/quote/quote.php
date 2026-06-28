<?php

/**
 * Pull-quote card
 *
 * PHP port of https://takumi.kane.tw/docs/templates#quote-template
 *
 * Usage:
 *   php examples/templates/quote.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

// The quote and attribution below are invented for this example — they are
// fictional and not a real testimonial from any person or company.
$quote = 'We swapped a whole headless-browser farm for a single PHP call.';
$author = 'Claude';
$role = 'AI Assistant';
$company = 'Code Company';

$ink = '#16140F';
$muted = '#6E6A60';
$accent = '#E5341F';

$html = <<<HTML
<div style="display: flex; flex-direction: column; width: 100%; height: 100%;
            background-color: #F5F3EC; color: {$ink}; padding: 80px 76px;
            font-family: Geist; justify-content: center;">

    <span style="display: flex; height: 72px; margin-left: -8px; margin-bottom: 8px;
                 font-size: 180px; line-height: 1; font-weight: 700; color: {$accent};">
        &ldquo;
    </span>

    <h1 style="display: flex; font-size: 64px; font-weight: 700; line-height: 1.12;
               letter-spacing: -0.02em; margin: 0; margin-bottom: 48px; max-width: 1000px; color: {$ink};">
        {$quote}
    </h1>

    <div style="display: flex; align-items: center; gap: 12px;">
        <span style="display: flex; font-size: 28px; font-weight: 700; color: {$ink};">{$author}</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">·</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">{$role}</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">·</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">{$company}</span>
    </div>
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => 1200,
    'height' => 630,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ],
]);

save_to_output($png, 'quote.png', $outputDir);
