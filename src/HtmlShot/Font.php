<?php

declare(strict_types=1);

namespace HtmlShot;

/**
 * Describes a font to load into a rendering Context.
 *
 * @example
 * $font = Font::fromFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400);
 * $font->loadInto($context);
 */
final class Font
{
    private function __construct(
        private readonly ?string $path,
        private readonly ?string $data,
        private readonly string $family,
        private readonly int $weight,
        private readonly string $style,
    ) {}

    /**
     * Create a Font descriptor that loads from a file path.
     *
     * @param  string  $path  Absolute path to a TTF / OTF / WOFF / WOFF2 file.
     * @param  string  $family  Override family name (empty = auto-detect).
     * @param  int  $weight  Override weight 1–1000 (0 = auto-detect).
     * @param  string  $style  Override style: "normal" | "italic" | "oblique".
     */
    public static function fromFile(
        string $path,
        string $family = '',
        int $weight = 0,
        string $style = ''
    ): self {
        return new self(path: $path, data: null, family: $family, weight: $weight, style: $style);
    }

    /**
     * Create a Font descriptor from raw bytes (e.g. from file_get_contents).
     *
     * @param  string  $data  Raw font file bytes.
     * @param  string  $family  Override family name (empty = auto-detect).
     * @param  int  $weight  Override weight 1–1000 (0 = auto-detect).
     * @param  string  $style  Override style: "normal" | "italic" | "oblique".
     */
    public static function fromData(
        string $data,
        string $family = '',
        int $weight = 0,
        string $style = ''
    ): self {
        return new self(path: null, data: $data, family: $family, weight: $weight, style: $style);
    }

    /**
     * Load this font into the given Context.
     *
     * @throws Exception\RuntimeException on failure.
     */
    public function loadInto(Context $context): void
    {
        if ($this->path !== null) {
            $context->loadFontFile($this->path, $this->family, $this->weight, $this->style);
        } elseif ($this->data !== null) {
            $context->loadFontData($this->data, $this->family, $this->weight, $this->style);
        }
    }
}
