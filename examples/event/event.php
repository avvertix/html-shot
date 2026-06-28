<?php

/**
 * Event announcement card
 *
 * PHP port of https://takumi.kane.tw/docs/templates#event-template
 *
 * Usage:
 *   php examples/templates/event.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$name = 'Shipping Rust to PHP: FFI in Production';
$track = 'Workshop';
$datetime = 'Thu, Sep 18, 2026 · 10:00 AM PT';
$location = 'Online';
$hostName = 'Claude';
$hostTitle = 'AI Agent, Code Company';

$ink = '#16140F';
$muted = '#6E6A60';
$accent = '#E5341F';

$html = <<<HTML
<div style="display: flex; flex-direction: column; width: 100%; height: 100%;
            background-color: #F5F3EC; color: {$ink}; padding: 80px 76px;
            font-family: Geist; justify-content: space-between;">

    <span style="display: flex; font-size: 20px; font-weight: 700; letter-spacing: 0.22em;
                 text-transform: uppercase; color: {$accent};">
        {$track}
    </span>

    <div style="display: flex; flex: 1; flex-direction: column; justify-content: center;">
        <span style="display: flex; font-size: 88px; font-weight: 800; line-height: 1.05;
                     letter-spacing: -0.035em; color: {$ink};">
            {$name}
        </span>
    </div>

    <div style="display: flex; align-items: flex-end; justify-content: space-between;">
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <span style="display: flex; font-size: 28px; font-weight: 600; color: {$ink};">{$datetime}</span>
            <span style="display: flex; font-size: 24px; color: {$muted};">{$location}</span>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="display: flex; font-size: 24px; font-weight: 600; color: {$ink};">{$hostName}</span>
            <span style="display: flex; font-size: 24px; color: {$muted};">·</span>
            <span style="display: flex; font-size: 24px; color: {$muted};">{$hostTitle}</span>
        </div>
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

save_to_output($png, 'event.png', $outputDir);
