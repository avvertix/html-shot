<?php

declare(strict_types=1);

namespace HtmlShot\Tests\Integration;

use HtmlShot\Context;
use HtmlShot\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Context and Renderer.
 * No local fixture files required.
 */
class RendererTest extends TestCase
{
    public function test_renders_solid_colour_correctly(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $bytes = $renderer->render(
            '<div style="background-color:red;width:100px;height:100px"></div>',
            200, 200, 'png'
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);

        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        $rgba = imagecolorsforindex($img, imagecolorat($img, 50, 50));
        $this->assertSame(255, $rgba['red']);
        $this->assertSame(0, $rgba['green']);
        $this->assertSame(0, $rgba['blue']);
        $this->assertSame(0, $rgba['alpha']);
    }

    public function test_context_can_be_reused_across_renders(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $html = '<div style="background:blue;width:100%;height:100%"></div>';

        $bytes1 = $renderer->render($html, 100, 100);
        $bytes2 = $renderer->render($html, 100, 100);

        $this->assertNotEmpty($bytes1);
        $this->assertNotEmpty($bytes2);

        $img1 = imagecreatefromstring($bytes1);
        $img2 = imagecreatefromstring($bytes2);

        $this->assertSame(imagesx($img1), imagesx($img2));
        $this->assertSame(imagesy($img1), imagesy($img2));
    }

    public function test_renders_with_multiple_stylesheets(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $bytes = $renderer->render(
            '<div class="box"></div>',
            200, 200, 'png', 0,
            [
                '.box { width: 80px; height: 80px; }',
                '.box { background-color: rgb(0, 0, 255); }',
            ]
        );

        $img = imagecreatefromstring($bytes);
        $this->assertNotFalse($img);

        $rgba = imagecolorsforindex($img, imagecolorat($img, 40, 40));
        $this->assertSame(0, $rgba['alpha'], 'Pixel should be fully opaque');
        $this->assertGreaterThan(200, $rgba['blue']);
    }

    public function test_renders_inline_style_tag(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $bytes = $renderer->render(
            '<style>.box{background-color:green;width:80px;height:80px}</style><div class="box"></div>',
            200, 200
        );

        $img = imagecreatefromstring($bytes);
        $rgba = imagecolorsforindex($img, imagecolorat($img, 40, 40));

        $this->assertGreaterThan(100, $rgba['green']);
        $this->assertLessThan(50, $rgba['red']);
        $this->assertSame(0, $rgba['alpha']);
    }

    public function test_output_dimensions_match_requested_size(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $bytes = $renderer->render('<div></div>', 800, 400);
        $img = imagecreatefromstring($bytes);

        $this->assertSame(800, imagesx($img));
        $this->assertSame(400, imagesy($img));
    }

    public function test_output_dimensions_scale_with_device_pixel_ratio(): void
    {
        $ctx = new Context;
        $renderer = new Renderer($ctx);

        $bytes = $renderer->render('<div></div>', 600, 314, 'png', 0, [], 2.0);
        $img = imagecreatefromstring($bytes);

        $this->assertSame(1200, imagesx($img));
        $this->assertSame(628, imagesy($img));
    }
}
