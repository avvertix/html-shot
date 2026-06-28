<?php

/**
 * Changelog / release notes card
 *
 * PHP port of https://takumi.kane.tw/docs/templates#changelog-template
 *
 * Usage:
 *   php examples/templates/changelog.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$version = 'v0.3.0';
$date = 'June 28, 2026';
$headline = 'Faster installation';
$bullets = [
    ['tag' => 'New', 'text' => 'Installation script'],
    ['tag' => 'Perf', 'text' => '30% smaller package footprint'],
    ['tag' => 'Fixed', 'text' => 'Out of the box Tailwind CSS'],
];

$ink = '#16140F';
$muted = '#6E6A60';
$accent = '#1F9D55';

$bulletRows = '';
foreach ($bullets as $b) {
    $tag = $b['tag'];
    $text = $b['text'];
    $bulletRows .= <<<HTML
        <div style="display: flex; align-items: center; gap: 28px;">
            <div style="display: flex; width: 92px; font-size: 20px; font-weight: 700;
                        letter-spacing: 0.12em; text-transform: uppercase; color: {$accent};">
                {$tag}
            </div>
            <span style="display: flex; font-size: 32px; font-weight: 500; color: {$ink};">
                {$text}
            </span>
        </div>
    HTML;
}

$html = <<<HTML
<div style="display: flex; flex-direction: column; width: 100%; height: 100%;
            background-color: #F5F3EC; color: {$ink}; padding: 80px 76px;
            font-family: Geist; justify-content: center;">

    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
        <span style="display: flex; font-size: 28px; font-weight: 700; color: {$accent};">{$version}</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">·</span>
        <span style="display: flex; font-size: 28px; color: {$muted};">{$date}</span>
    </div>

    <h1 style="display: flex; font-size: 72px; font-weight: 800; line-height: 1.05;
               letter-spacing: -0.03em; margin: 0; margin-bottom: 56px; max-width: 980px; color: {$ink};">
        {$headline}
    </h1>

    <div style="display: flex; flex-direction: column; gap: 24px;">
        {$bulletRows}
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

save_to_output($png, 'changelog.png', $outputDir);
