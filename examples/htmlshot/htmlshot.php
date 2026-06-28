<?php

/**
 * HtmlShot Open Graph
 *
 * Usage:
 *   php examples/templates/htmlshot/htmlshot.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

$owner = 'avvertix';
$name = 'html-shot';
$description = 'HTML to image rendering for PHP.<br/>Generate OG image with native PHP.';
$stars = '0';
$forks = '0';

$ink = '#16140F';
$muted = '#6E6A60';
$mono = "'Geist Mono', monospace";

$rows = '';
for ($i = 0; $i < 18; $i++) {
    $marginLeft = (($i * 137) % 600);
    $items = '';
    for ($j = 0; $j < 15; $j++) {
        $items .= <<<'HTML'
<div style="display: flex; align-items: center; flex-shrink: 0; gap: 0.25em; margin-left: -1rem;">
  <span style="margin-right: 1rem;">Shake.</span>
  <span style="margin-right: 1rem;">Render.</span>
  <span style="margin-right: 1rem;">Refresh.</span>
  <span>&nbsp;</span>
</div>
HTML;
    }
    $rows .= <<<HTML
<div style="margin: 0; font-size: 64px; line-height: 0.9; font-weight: 900; color: #f4f4f0;
            font-family: Geist, sans-serif; white-space: nowrap; display: flex;
            align-items: center; margin-left: -{$marginLeft}px;">
  {$items}
</div>
HTML;
}

$html = <<<HTML
<div style="display: flex; flex-direction: column; width: 100%; height: 100%;
            background-color: #F5F3EC; color: {$ink}; padding: 80px 76px;
            font-family: Geist; justify-content: center;">

<!-- top: -450px; -->
    <div style="mix-blend-mode: multiply;position: absolute; display: flex; flex-direction: column; justify-content: center;
              left: -600px; 
              top: 200px;
              width: 2400px; height: 1600px;  transform: rotate(-12deg);">
    {$rows}
  </div>


    <div style="position:absolute;right:-66%;top:-36px;height:100%;width:100%">
        <img src="{$assetsImages}/fresh-htmlshot-no-bg.png" alt="" style="height:100%">
    </div>

    <span style="display: flex; font-size: 40px; font-weight: 500; font-family: {$mono};
                 color: {$muted}; letter-spacing: -0.01em;">
        {$owner}/
    </span>
    <span style="display: flex; font-size: 132px; font-weight: 700; font-family: {$mono};
                 color: {$ink}; line-height: 0.96; letter-spacing: -0.045em; margin-top: 4px;">
        {$name}
    </span>
    <span style="display: flex; flex-direction: column; font-size: 32px; font-weight: 400; color: #37352F;
                 line-height: 1.36; max-width: 920px; margin-top: 32px;">
        {$description}
    </span>

    <div style="display: flex; align-items: center; gap: 16px; margin-top: 44px;">
        <!-- <div style="display: flex; align-items: baseline; gap: 8px;">
            <span style="display: flex; font-size: 28px; font-weight: 700; color: {$ink};">{$stars}</span>
            <span style="display: flex; font-size: 28px; color: {$muted};">stars</span>
        </div>
        <span style="display: flex; font-size: 28px; color: {$muted};">·</span>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span style="display: flex; font-size: 28px; font-weight: 700; color: {$ink};">{$forks}</span>
            <span style="display: flex; font-size: 28px; color: {$muted};">forks</span>
        </div>
        <span style="display: flex; font-size: 28px; color: {$muted};">·</span> -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; width: 20px; height: 20px; border-radius: 50%;
                        background-color: #5b21b6;"></div>
            <span style="display: flex; font-size: 28px; font-weight: 600; color: #5b21b6;">PHP</span>
            +
            <div style="display: flex; width: 20px; height: 20px; border-radius: 50%;
                        background-color: #9f1239;"></div>
            <span style="display: flex; font-size: 28px; font-weight: 600; color: #9f1239;">Rust</span>
        </div>
    </div>
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => 1280,
    'height' => 640,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
        Font::fromFile("{$fontsPath}/GeistMono/variable/GeistMono[wght].ttf", 'Geist Mono'),
    ],
]);

save_to_output($png, 'htmlshot.png', $outputDir);
