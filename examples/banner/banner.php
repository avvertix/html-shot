<?php

use HtmlShot\Context;
use HtmlShot\Renderer;

require __DIR__.'/../common.php';

$outputDir = __DIR__.'/output';

/**
 * A release announcement banner
 *
 * Usage:
 *   php examples/banner/banner.php
 */

// Inline SVG logo used in the watermark rows (mirrors the <Logo /> component)
$logo = <<<'SVG'
<svg width="52" height="52" viewBox="0 0 128 128" style="margin-right: 2rem; flex-shrink: 0;">
  <path fill="#18181b" d="M114.3 14.1c1.1.9 3.2 2.7 4.2 4.5s.9 3.5.8 4.8-.4 2.3-2 4.3c-1.7 2-4.7 5-12.8 13.7-8.1 8.8-21.4 23.4-35.3 38.4s-28.6 30.5-36.4 38.7-8.8 8.8-10 9.2-2.5.4-4.1 0-3.5-1.2-6.5-3.8-7.1-6.9-9.4-10S.1 108.8.1 107c0-1.7.4-3.4 5.3-8.6s14.3-13.9 30.5-28.9c16.1-14.9 39-36.1 51-47.2s13.2-12 14.7-12.7 3.2-1.1 4.8-.9 2.8 1 3.9 1.9a32 32 0 0 1 2.5 2.1l.4.5z"/>
  <path fill="#18181b" d="M79 .5C65.3 3.1 46.9 23.4 56.8 36.3c3.3 4.3 5.1 6.7 9.3 9.7 10.2 7.3 39.1 31 53.1 26.9 12-3.5 9.4-16.9 5.6-25.8-1.3-3-25.7-52.8-45.8-46.6"/>
</svg>
SVG;

$COMPONENT = [
    'name' => 'v1',
    'width' => 1200,
    'height' => 675,
    'fonts' => [
        ['path' => __DIR__.'/../../tests/fonts/Geist/variable/Geist[wght].ttf',         'family' => 'Geist'],
        ['path' => __DIR__.'/../../tests/fonts/GeistMono/variable/GeistMono[wght].ttf', 'family' => 'Geist Mono'],
    ],
    'html' => static function () use ($assetsImages): string {
        // Build the 18-row repeated pattern
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
<div style="margin: 0; font-size: 64px; line-height: 0.9; font-weight: 900; color: #18181b;
            font-family: Geist, sans-serif; white-space: nowrap; display: flex;
            align-items: center; margin-left: -{$marginLeft}px;">
  {$items}
</div>
HTML;
        }

        return <<<HTML
<div style="width: 100%; height: 100%; background-color: #14532d; display: flex;
            align-items: center; justify-content: center; position: relative; overflow: hidden;
            font-family: Geist, sans-serif;">

  <!-- Watermark rows (rotated) -->
  <div style="mix-blend-mode: screen;position: absolute; display: flex; flex-direction: column; justify-content: center;
              width: 2400px; height: 1600px; left: -600px; top: -450px; transform: rotate(-12deg);">
    {$rows}
  </div>

  <!-- Foreground content -->
  <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;
              position: relative; z-index: 10;">
              <img src="{$assetsImages}/fresh-htmlshot-no-bg.png" style="height:260px;">
    <h1 style="margin: 0; color: #fff; font-size: 120px; font-weight: 700;
               letter-spacing: -0.05em; line-height: 1;">v0.3.0 Released</h1>
    <div style="margin-top: 3rem; display: flex; align-items: center; padding: 1rem 2rem;
                background-color: #052e16; border-radius: 9999px;">
      <span style="font-family: Geist Mono, monospace; color: #f0fdf4; font-size: 32px; letter-spacing: -0.02em;">composer require avvertix/html-shot</span>
    </div>
  </div>

</div>
HTML;
    },
];

$ctx = new Context;
foreach ($COMPONENT['fonts'] as $f) {
    $ctx->loadFontFile($f['path'], family: $f['family']);
}
$renderer = new Renderer($ctx);
$html = ($COMPONENT['html'])();

$png = $renderer->render($html, $COMPONENT['width'], $COMPONENT['height'], 'png');

save_to_output($png, 'banner.png', $outputDir);

$webp = $renderer->render($html, $COMPONENT['width'], $COMPONENT['height'], 'webp', devicePixelRatio: 2.0);

save_to_output($webp, 'banner@2x.webp', $outputDir);
