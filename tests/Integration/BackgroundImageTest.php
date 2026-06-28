<?php

declare(strict_types=1);

namespace HtmlShot\Tests\Integration;

use HtmlShot\Context;
use HtmlShot\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for background-image and <img src> rendering.
 * Requires image fixture files from examples/assets/images/.
 */
class BackgroundImageTest extends TestCase
{
    /** Photographic JPEG fixture: full-bleed, every pixel opaque and colored. */
    private const JPEG = 'martin-martz-W0NRebXbsjM-unsplash.jpg';

    /** Fill colour for the live-generated SVG fixture (#3b82f6). */
    private const SVG_FILL = [59, 130, 246];

    private static string $imagesDir;

    public static function setUpBeforeClass(): void
    {
        self::$imagesDir = dirname(__DIR__, 2).'/examples/assets/images';
    }

    public function test_background_image_jpeg(): void
    {
        $jpg = self::$imagesDir.'/'.self::JPEG;

        if (! file_exists($jpg)) {
            $this->markTestSkipped(self::JPEG.' not found in examples/assets/images');
        }

        $ctx = new Context;
        $bytes = (new Renderer($ctx))->render(
            '<div style="background-image:url('.$jpg.');background-size:cover;width:200px;height:200px"></div>',
            200, 200
        );

        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        $rgba = imagecolorsforindex($img, imagecolorat($img, 100, 100));
        $this->assertSame(0, $rgba['alpha'], 'Pixel should be fully opaque');
        $this->assertGreaterThan(10, $rgba['red'] + $rgba['green'] + $rgba['blue'], 'Pixel should be non-black');
    }

    public function test_background_image_svg(): void
    {
        // Generate a solid-fill SVG live and feed it in as a data URI, so the
        // test owns its fixture and can assert the exact colour that renders.
        $dataUri = $this->solidSvgDataUri(...self::SVG_FILL);

        $ctx = new Context;
        $bytes = (new Renderer($ctx))->render(
            '<div style="background-image:url('.$dataUri.');background-size:cover;width:200px;height:200px"></div>',
            200, 200
        );

        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        [$red, $green, $blue] = self::SVG_FILL;
        $rgba = imagecolorsforindex($img, imagecolorat($img, 100, 100));
        $this->assertSame(0, $rgba['alpha'], 'Pixel should be fully opaque');
        $this->assertEqualsWithDelta($red, $rgba['red'], 4, 'Red channel should match the SVG fill');
        $this->assertEqualsWithDelta($green, $rgba['green'], 4, 'Green channel should match the SVG fill');
        $this->assertEqualsWithDelta($blue, $rgba['blue'], 4, 'Blue channel should match the SVG fill');
    }

    public function test_img_src_jpeg(): void
    {
        $jpg = self::$imagesDir.'/'.self::JPEG;

        if (! file_exists($jpg)) {
            $this->markTestSkipped(self::JPEG.' not found in examples/assets/images');
        }

        $ctx = new Context;
        $bytes = (new Renderer($ctx))->render(
            '<img src="'.$jpg.'" width="200" height="200" />',
            200, 200
        );

        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        $rgba = imagecolorsforindex($img, imagecolorat($img, 100, 100));
        $this->assertSame(0, $rgba['alpha'], 'Pixel should be fully opaque');
        $this->assertGreaterThan(10, $rgba['red'] + $rgba['green'] + $rgba['blue'], 'Pixel should be non-black');
    }

    /**
     * Build a 200x200 solid-fill SVG and return it as a base64 data URI.
     */
    private function solidSvgDataUri(int $red, int $green, int $blue): string
    {
        $hex = sprintf('#%02x%02x%02x', $red, $green, $blue);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">'
            .'<rect width="200" height="200" fill="'.$hex.'"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
