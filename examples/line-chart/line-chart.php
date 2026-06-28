<?php

/**
 * Line chart with a dither effect.
 *
 * The chart geometry (area fill, line, point markers) is drawn with an inline
 * SVG whose points are computed from a data array. Title and axis labels are
 * HTML laid over the same 1200x630 coordinate space, on top of a smooth
 * gradient background.
 * 
 * Usage:
 *   php examples/line-chart/line-chart.php
 */

require __DIR__.'/../common.php';

use HtmlShot\Font;
use HtmlShot\HtmlShot;

$outputDir = __DIR__.'/output';

// ── Data ─────────────────────────────────────────────────────────────────────
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$data = [12, 19, 15, 27, 24, 38, 35, 49, 52, 47, 63, 71]; // renders, in thousands
$gridValues = [0, 20, 40, 60, 80];
$maxScale = 80;

// ── Plot geometry (matches the 1200x630 canvas) ──────────────────────────────
$W = 1200;
$H = 630;
$plotL = 90;
$plotR = 1140;
$plotT = 150;
$plotB = 540;
$plotW = $plotR - $plotL;
$plotH = $plotB - $plotT;

$n = count($data);
$x = static fn (int $i): float => $plotL + $plotW * $i / ($n - 1);
$y = static fn (float $v): float => $plotB - $plotH * ($v / $maxScale);

// Point list for the line + area path.
$points = [];
for ($i = 0; $i < $n; $i++) {
    $points[] = round($x($i), 1).','.round($y($data[$i]), 1);
}
$polyline = implode(' ', $points);
$areaPath = "M {$plotL},{$plotB} L ".implode(' L ', $points)." L {$plotR},{$plotB} Z";

// Horizontal grid lines.
$grid = '';
foreach ($gridValues as $v) {
    $gy = round($y($v), 1);
    $grid .= "<line x1=\"{$plotL}\" y1=\"{$gy}\" x2=\"{$plotR}\" y2=\"{$gy}\" stroke=\"#262e4d\" stroke-width=\"1\" />";
}

// Point markers.
$markers = '';
for ($i = 0; $i < $n; $i++) {
    $cx = round($x($i), 1);
    $cy = round($y($data[$i]), 1);
    $markers .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"5\" fill=\"#0b1020\" stroke=\"#9db4ff\" stroke-width=\"3\" />";
}

$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$W} {$H}" width="{$W}" height="{$H}">
    <defs>
        <linearGradient id="area" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#7c93ff" stop-opacity="0.55" />
            <stop offset="1" stop-color="#7c93ff" stop-opacity="0.02" />
        </linearGradient>
    </defs>
    {$grid}
    <path d="{$areaPath}" fill="url(#area)" />
    <polyline points="{$polyline}" fill="none" stroke="#9db4ff" stroke-width="4"
              stroke-linejoin="round" stroke-linecap="round" />
    {$markers}
</svg>
SVG;

// ── Axis labels positioned over the same coordinate space ────────────────────
$yLabels = '';
foreach ($gridValues as $v) {
    $top = round($y($v) - 13, 1);
    $yLabels .= "<span style=\"position: absolute; left: 0; top: {$top}px; width: 66px;
                 text-align: right; font-size: 20px; color: #6b7399;\">{$v}k</span>";
}
$xLabels = '';
for ($i = 0; $i < $n; $i++) {
    $left = round($x($i) - 40, 1);
    $xLabels .= "<span style=\"position: absolute; left: {$left}px; top: 552px; width: 80px;
                 text-align: center; font-size: 20px; color: #6b7399;\">{$months[$i]}</span>";
}

$html = <<<HTML
<div style="position: relative; display: flex; width: 100%; height: 100%; font-family: Geist;
            background-image: linear-gradient(160deg, #0b1020 0%, #161d3a 55%, #20284b 100%);">

    <span style="position: absolute; left: 90px; top: 50px; font-size: 36px; font-weight: 700;
                 color: #f4f6ff; letter-spacing: -0.01em;">Monthly active renders</span>
    <span style="position: absolute; left: 90px; top: 100px; font-size: 22px; color: #8b93b5;">
        2026 · in thousands
    </span>

    {$svg}
    {$yLabels}
    {$xLabels}
</div>
HTML;

$png = HtmlShot::render($html, [
    'width' => $W,
    'height' => $H,
    'format' => 'png',
    'fonts' => [
        Font::fromFile("{$fontsPath}/Geist/variable/Geist[wght].ttf", 'Geist'),
    ],
]);

save_to_output($png, 'line-chart.png', $outputDir);
