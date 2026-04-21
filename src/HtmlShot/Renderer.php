<?php

declare(strict_types=1);

namespace HtmlShot;

/**
 * Renders HTML strings to images using a shared rendering Context.
 *
 * @example
 * $ctx = new Context();
 * $ctx->loadFontFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400);
 *
 * $renderer = new Renderer($ctx);
 * $png = $renderer->render('<div style="font-size:48px">Hello!</div>');
 * file_put_contents('output.png', $png);
 */
final class Renderer
{
    public function __construct(
        private readonly Context $context,
    ) {}

    /**
     * Render an HTML string to image bytes.
     *
     * @param string  $html              HTML content to render.
     * @param int     $width             Logical canvas width in pixels (default 1200).
     * @param int     $height            Logical canvas height in pixels (default 628).
     * @param string  $format            Output format: "png" | "webp" | "jpeg" (default "png").
     * @param int     $quality           Encoding quality 1–100 for JPEG/WebP (0 = library default).
     * @param array   $stylesheets       Additional CSS stylesheets to apply.
     * @param float   $devicePixelRatio  Output scale factor: 1.0 = normal, 2.0 = HiDPI/Retina.
     *                                   Layout stays at $width×$height logical px; the output bitmap
     *                                   is ($width * dpr) × ($height * dpr) physical pixels.
     *
     * @return string Raw image bytes.
     * @throws Exception\RuntimeException on render failure.
     */
    public function render(
        string $html,
        int $width = 1200,
        int $height = 628,
        string $format = 'png',
        int $quality = 0,
        array $stylesheets = [],
        float $devicePixelRatio = 1.0,
    ): string {
        $ffi = Ffi::instance();

        // Build a C array of char* pointers for the stylesheets
        [$cssptrs, $cssBufs, $cssLen] = self::buildStringArray($stylesheets);

        // Physical output dimensions: logical * DPR (matches TypeScript behaviour)
        $physicalWidth  = (int) round($width  * $devicePixelRatio);
        $physicalHeight = (int) round($height * $devicePixelRatio);

        $output = $ffi->takumi_render_html(
            $this->context->ffiHandle(),
            Ffi::cstring($html),
            $cssLen > 0 ? $cssptrs : null,
            $cssLen,
            $physicalWidth,
            $physicalHeight,
            (float) $devicePixelRatio,
            Ffi::cstring($format),
            $quality,
        );

        // $cssBufs must stay in scope until the FFI call returns
        unset($cssBufs, $cssptrs);

        if ($output === null || \FFI::isNull($output)) {
            Ffi::throwLastError('Renderer::render');
        }

        try {
            $lenPtr = $ffi->new('size_t');
            $bytesPtr = $ffi->takumi_output_bytes($output, \FFI::addr($lenPtr));

            if ($bytesPtr === null || \FFI::isNull($bytesPtr)) {
                Ffi::throwLastError('Renderer::render (output_bytes)');
            }

            return \FFI::string($bytesPtr, (int) $lenPtr->cdata);
        } finally {
            $ffi->takumi_output_free($output);
        }
    }

    /**
     * Build a C `char*[]` array from a PHP string array.
     *
     * Returns [$ptrs, $bufs, $count].
     * Both $ptrs and $bufs must remain in scope during the FFI call.
     *
     * @param string[] $strings
     * @return array{0: \FFI\CData|null, 1: \FFI\CData[], 2: int}
     */
    private static function buildStringArray(array $strings): array
    {
        $count = count($strings);
        if ($count === 0) {
            return [null, [], 0];
        }

        $ffi = Ffi::instance();
        $ptrs = $ffi->new("char*[{$count}]", false);
        $bufs = [];

        foreach (array_values($strings) as $i => $s) {
            $len = strlen($s);
            $buf = $ffi->new("char[" . ($len + 1) . "]", false);
            \FFI::memcpy($buf, $s, $len);
            $buf[$len] = "\0";
            $ptrs[$i] = $buf;
            $bufs[] = $buf;
        }

        return [$ptrs, $bufs, $count];
    }
}
