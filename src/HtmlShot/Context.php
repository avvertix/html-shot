<?php

declare(strict_types=1);

namespace HtmlShot;

use FFI\CData;

/**
 * A rendering context that holds font caches and persistent image state.
 *
 * Reuse the same Context across multiple render calls to share font data
 * and avoid reloading fonts on every render.
 *
 * @example
 * $ctx = new Context();
 * $ctx->loadFontFile('/path/to/Inter-Regular.ttf', family: 'Inter', weight: 400);
 * $ctx->loadFontFile('/path/to/Inter-Bold.ttf',    family: 'Inter', weight: 700);
 */
final class Context
{
    private CData $handle;

    public function __construct()
    {
        $this->handle = Ffi::assertHandle(
            Ffi::instance()->takumi_context_new(),
            'Context::__construct'
        );
    }

    public function __destruct()
    {
        Ffi::instance()->takumi_context_free($this->handle);
    }

    /** @internal Used by Renderer */
    public function ffiHandle(): CData
    {
        return $this->handle;
    }

    /**
     * Load a font from a file path.
     *
     * @param  string  $path  Absolute path to a TTF / OTF / WOFF / WOFF2 file.
     * @param  string  $family  Override family name (empty = auto-detect from font metadata).
     * @param  int  $weight  Override weight 1–1000 (0 = auto-detect).
     * @param  string  $style  Override style: "normal" | "italic" | "oblique" (empty = auto-detect).
     *
     * @throws Exception\RuntimeException on failure.
     */
    public function loadFontFile(
        string $path,
        string $family = '',
        int $weight = 0,
        string $style = ''
    ): void {
        $result = Ffi::instance()->takumi_context_load_font_file(
            $this->handle,
            Ffi::cstring($path),
            $family !== '' ? Ffi::cstring($family) : null,
            $weight,
            $style !== '' ? Ffi::cstring($style) : null,
        );
        if ($result !== 0) {
            Ffi::throwLastError('Context::loadFontFile');
        }
    }

    /**
     * Load a font from raw bytes (e.g. read via file_get_contents).
     *
     * @param  string  $data  Raw font file bytes.
     * @param  string  $family  Override family name (empty = auto-detect).
     * @param  int  $weight  Override weight 1–1000 (0 = auto-detect).
     * @param  string  $style  Override style: "normal" | "italic" | "oblique" (empty = auto-detect).
     *
     * @throws Exception\RuntimeException on failure.
     */
    public function loadFontData(
        string $data,
        string $family = '',
        int $weight = 0,
        string $style = ''
    ): void {
        $ffi = Ffi::instance();
        $len = strlen($data);
        $buf = $ffi->new("uint8_t[{$len}]", false);
        \FFI::memcpy($buf, $data, $len);

        $result = $ffi->takumi_context_load_font_data(
            $this->handle,
            $buf,
            $len,
            $family !== '' ? Ffi::cstring($family) : null,
            $weight,
            $style !== '' ? Ffi::cstring($style) : null,
        );
        if ($result !== 0) {
            Ffi::throwLastError('Context::loadFontData');
        }
    }
}
