<?php

declare(strict_types=1);

namespace HtmlShot;

/**
 * One-call façade for rendering HTML to an image.
 *
 * For repeated rendering, construct a `Context` once, load fonts into it,
 * then create a `Renderer` and call `render()` directly — that avoids
 * re-loading fonts on every call.
 *
 * @example
 * // Simplest usage — fonts loaded each time
 * $png = HtmlShot::render('<div style="font-size:48px">Hello!</div>', [
 *     'width'  => 1200,
 *     'height' => 628,
 *     'fonts'  => [
 *         Font::fromFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400),
 *         Font::fromFile('/fonts/Inter-Bold.ttf',    family: 'Inter', weight: 700),
 *     ],
 *     'stylesheets' => ['body { background: #fff; }'],
 * ]);
 * file_put_contents('card.png', $png);
 */
final class HtmlShot
{
    /**
     * Render HTML to image bytes.
     *
     * @param  string  $html  HTML content to render.
     * @param array{
     *     width?: int,
     *     height?: int,
     *     format?: string,
     *     quality?: int,
     *     stylesheets?: string[],
     *     fonts?: Font[],
     *     devicePixelRatio?: float,
     * } $options
     * @return string Raw image bytes.
     *
     * @throws Exception\RuntimeException on failure.
     */
    public static function render(string $html, array $options = []): string
    {
        $context = new Context;

        foreach ($options['fonts'] ?? [] as $font) {
            if ($font instanceof Font) {
                $font->loadInto($context);
            }
        }

        $renderer = new Renderer($context);

        return $renderer->render(
            html: $html,
            width: (int) ($options['width'] ?? 1200),
            height: (int) ($options['height'] ?? 628),
            format: (string) ($options['format'] ?? 'png'),
            quality: (int) ($options['quality'] ?? 0),
            stylesheets: (array) ($options['stylesheets'] ?? []),
            devicePixelRatio: (float) ($options['devicePixelRatio'] ?? 1.0),
        );
    }
}
