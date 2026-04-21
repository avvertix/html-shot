<?php

declare(strict_types=1);

namespace HtmlShot\Tests\Integration;

use HtmlShot\Context;
use HtmlShot\Font;
use HtmlShot\HtmlShot;
use HtmlShot\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Font and Context font-loading methods.
 * Uses Geist variable fonts from tests/fonts/.
 */
class FontTest extends TestCase
{
    private const GEIST = __DIR__.'/../fonts/Geist/variable/Geist[wght].ttf';

    private const GEIST_ITALIC = __DIR__.'/../fonts/Geist/variable/Geist-Italic[wght].ttf';

    private const GEIST_MONO = __DIR__.'/../fonts/GeistMono/variable/GeistMono[wght].ttf';

    // ── Font::fromFile ───────────────────────────────────────────────────────

    public function test_font_from_file_loads_into_context(): void
    {
        $ctx = new Context;
        Font::fromFile(self::GEIST, family: 'Geist', weight: 400)->loadInto($ctx);

        $bytes = (new Renderer($ctx))->render(
            '<div style="font-family:Geist;font-size:24px;color:black">Test</div>',
            300, 100
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    // ── Font::fromData ───────────────────────────────────────────────────────

    public function test_font_from_data_loads_into_context(): void
    {
        $data = file_get_contents(self::GEIST);
        $this->assertNotFalse($data);

        $ctx = new Context;
        Font::fromData($data, family: 'Geist', weight: 400)->loadInto($ctx);

        $bytes = (new Renderer($ctx))->render(
            '<div style="font-family:Geist;font-size:24px;color:black">Test</div>',
            300, 100
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    // ── Context::loadFontFile ────────────────────────────────────────────────

    public function test_context_load_font_file(): void
    {
        $ctx = new Context;
        $ctx->loadFontFile(self::GEIST, family: 'Geist', weight: 400);

        $bytes = (new Renderer($ctx))->render(
            '<div style="font-family:Geist;font-size:20px;color:black">Hello</div>',
            300, 100
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    // ── Context::loadFontData ────────────────────────────────────────────────

    public function test_context_load_font_data(): void
    {
        $data = file_get_contents(self::GEIST);
        $this->assertNotFalse($data);

        $ctx = new Context;
        $ctx->loadFontData($data, family: 'Geist', weight: 400);

        $bytes = (new Renderer($ctx))->render(
            '<div style="font-family:Geist;font-size:20px;color:black">Hello</div>',
            300, 100
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    public function test_multiple_font_variants_load_into_context(): void
    {
        $ctx = new Context;
        $ctx->loadFontFile(self::GEIST, family: 'Geist', weight: 400);
        $ctx->loadFontFile(self::GEIST_ITALIC, family: 'Geist', weight: 400, style: 'italic');
        $ctx->loadFontFile(self::GEIST_MONO, family: 'GeistMono', weight: 400);

        $bytes = (new Renderer($ctx))->render(
            '<div style="font-family:Geist;font-size:20px;color:black">Hello <em>world</em></div>',
            300, 100
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }

    public function test_loading_nonexistent_font_file_throws(): void
    {
        $ctx = new Context;

        try {
            $ctx->loadFontFile('/nonexistent/path/to/font.ttf');
            $this->fail('Expected an exception when loading a non-existent font file');
        } catch (\Throwable) {
            $this->addToAssertionCount(1);
        }
    }

    // ── HtmlShot facade with fonts option ───────────────────────────────────

    public function test_htmlshot_render_with_font_option(): void
    {
        $bytes = HtmlShot::render(
            '<div style="font-family:Geist;font-size:32px;color:black">Hello</div>',
            [
                'width' => 400,
                'height' => 100,
                'fonts' => [
                    Font::fromFile(self::GEIST, family: 'Geist', weight: 400),
                ],
            ]
        );

        $this->assertStringStartsWith("\x89PNG", $bytes);
    }
}
