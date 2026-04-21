<?php

declare(strict_types=1);

namespace HtmlShot\Tests\Integration;

use HtmlShot\HtmlShot;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the HtmlShot façade.
 * No local fixture files required.
 */
class HtmlShotTest extends TestCase
{
    public function test_render_returns_png_bytes(): void
    {
        $bytes = HtmlShot::render('<div style="color:black">hello</div>', [
            'width' => 200,
            'height' => 100,
        ]);

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith("\x89PNG", $bytes, 'Output should be a valid PNG');
    }

    public function test_render_webp_format(): void
    {
        $bytes = HtmlShot::render('<div style="background:red;width:100px;height:100px"></div>', [
            'width' => 200,
            'height' => 200,
            'format' => 'webp',
        ]);

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('RIFF', $bytes);
        $this->assertStringContainsString('WEBP', substr($bytes, 0, 12));
    }

    public function test_render_jpeg_format(): void
    {
        $bytes = HtmlShot::render('<div style="background:blue;width:100%;height:100%"></div>', [
            'width' => 200,
            'height' => 200,
            'format' => 'jpeg',
            'quality' => 80,
        ]);

        $this->assertNotEmpty($bytes);
        $this->assertSame("\xFF\xD8\xFF", substr($bytes, 0, 3));
    }

    public function test_render_with_stylesheet_option(): void
    {
        $bytes = HtmlShot::render('<div class="box"></div>', [
            'width' => 200,
            'height' => 200,
            'stylesheets' => ['.box { background-color: green; width: 80px; height: 80px; }'],
        ]);

        $this->assertNotEmpty($bytes);
        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        $rgba = imagecolorsforindex($img, imagecolorat($img, 40, 40));
        $this->assertGreaterThan(100, $rgba['green'], 'Pixel should be green');
        $this->assertLessThan(50, $rgba['red']);
        $this->assertSame(0, $rgba['alpha'], 'Pixel should be fully opaque');
    }

    public function test_render_with_device_pixel_ratio(): void
    {
        $bytes1x = HtmlShot::render('<div style="background:red;width:100%;height:100%"></div>', [
            'width' => 100,
            'height' => 100,
            'devicePixelRatio' => 1.0,
        ]);

        $bytes2x = HtmlShot::render('<div style="background:red;width:100%;height:100%"></div>', [
            'width' => 100,
            'height' => 100,
            'devicePixelRatio' => 2.0,
        ]);

        $img1x = imagecreatefromstring($bytes1x);
        $img2x = imagecreatefromstring($bytes2x);

        $this->assertSame(100, imagesx($img1x));
        $this->assertSame(100, imagesy($img1x));
        $this->assertSame(200, imagesx($img2x));
        $this->assertSame(200, imagesy($img2x));
    }
}
