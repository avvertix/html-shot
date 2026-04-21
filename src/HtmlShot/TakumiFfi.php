<?php

declare(strict_types=1);

namespace HtmlShot;

use FFI\CData;

/**
 * Singleton that loads and holds the FFI instance for the takumi_php shared library.
 *
 * Resolves the compiled library and header from the `lib/` and `include/` directories
 * relative to the package root. PHP must have `ext-ffi` enabled.
 *
 * @method ?CData takumi_get_last_error()
 * @method void takumi_clear_last_error()
 * @method ?CData takumi_context_new()
 * @method void takumi_context_free(CData $handle)
 * @method int takumi_context_load_font_data(CData $handle, CData $data, int $data_len, ?CData $family, int $weight, ?CData $style)
 * @method int takumi_context_load_font_file(CData $handle, CData $path, ?CData $family, int $weight, ?CData $style)
 * @method ?CData takumi_output_bytes(CData $handle, CData $out_len)
 * @method int takumi_output_size(CData $handle)
 * @method int takumi_output_save(CData $handle, CData $path)
 * @method void takumi_output_free(CData $handle)
 * @method ?CData takumi_render_html(CData $ctx, CData $html, ?CData $stylesheets, int $stylesheets_len, int $width, int $height, float $device_pixel_ratio, CData $format, int $quality)
 * @method ?CData new(string $type, bool $owned = true, bool $persistent = false)
 */
final class TakumiFfi
{
    private static ?self $singleton = null;

    private function __construct(
        private readonly \FFI $ffi,
    ) {}

    /**
     * Return the shared FFI instance, loading it on first call.
     *
     * @throws \RuntimeException if the library or header cannot be found.
     */
    public static function instance(): self
    {
        if (self::$singleton === null) {
            self::$singleton = new self(self::load());
        }

        return self::$singleton;
    }

    /** @param mixed[] $args */
    public function __call(string $name, array $args): mixed
    {
        return $this->ffi->$name(...$args);
    }

    private static function load(): \FFI
    {
        $packageRoot = dirname(__DIR__, 2);
        $headerFile = $packageRoot.'/include/takumi_php.h';
        $libFile = self::resolveLibrary($packageRoot);

        if (! file_exists($headerFile)) {
            throw new \RuntimeException(
                "takumi_php header not found at: {$headerFile}\n".
                'Build the Rust library first: cd rust && cargo build --release'
            );
        }

        if ($libFile === null) {
            throw new \RuntimeException(
                "takumi_php shared library not found in: {$packageRoot}/lib/\n".
                'Build the Rust library first: cd rust && cargo build --release'
            );
        }

        return \FFI::cdef(self::processHeader($headerFile), $libFile);
    }

    /**
     * Locate the platform-appropriate shared library file.
     */
    private static function resolveLibrary(string $root): ?string
    {
        $libDir = $root.'/lib';

        $candidates = match (PHP_OS_FAMILY) {
            'Windows' => [
                $libDir.'/takumi_php.dll',
            ],
            'Darwin' => [
                $libDir.'/libtakumi_php.dylib',
                $libDir.'/libtakumi_php.so',
            ],
            default => [
                $libDir.'/libtakumi_php.so',
            ],
        };

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Strip preprocessor directives that \FFI::cdef does not support.
     */
    private static function processHeader(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read header file: {$path}");
        }

        // Remove #include guards and other preprocessor lines
        $content = preg_replace('/^#.*$/m', '', $content);

        return $content;
    }

    /**
     * Convert a PHP string to a null-terminated C string (char*).
     */
    public static function cstring(string $s): CData
    {
        $ffi = self::instance();
        $len = strlen($s);
        $buf = $ffi->new('char['.($len + 1).']', false);
        if ($buf === null) {
            throw new Exception\RuntimeException('FFI memory allocation failed');
        }
        \FFI::memcpy($buf, $s, $len);
        $buf[$len] = "\0";

        return $buf;
    }

    /**
     * Throw a RuntimeException with the last FFI error message, then clear it.
     *
     * @throws Exception\RuntimeException always
     */
    public static function throwLastError(string $context = ''): never
    {
        $ffi = self::instance();
        $ptr = $ffi->takumi_get_last_error();
        $msg = ($ptr !== null && ! \FFI::isNull($ptr)) ? \FFI::string($ptr) : 'unknown error';
        $ffi->takumi_clear_last_error();
        $prefix = $context !== '' ? "{$context}: " : '';
        throw new Exception\RuntimeException("{$prefix}{$msg}");
    }

    /**
     * Assert that a CData handle is non-null; throw with the last error otherwise.
     *
     * @throws Exception\RuntimeException on null handle
     */
    public static function assertHandle(?CData $handle, string $context = ''): CData
    {
        if ($handle === null || \FFI::isNull($handle)) {
            self::throwLastError($context);
        }

        return $handle;
    }
}
