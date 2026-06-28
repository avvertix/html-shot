# Getting started

## Requirements

- PHP 8.3 or higher
- The FFI extension enabled (`ext-ffi`)

Check that FFI is active:

```bash
php -m | grep FFI
```

If it is missing, enable it in your `php.ini` and restart PHP:

```ini
extension=ffi
ffi.enable=true
```

## Install

```bash
composer require avvertix/html-shot
```

The package renders through a compiled native library. Download the binary that
matches your platform and package version with the bundled console command:

```bash
vendor/bin/htmlshot install
```

This fetches the correct binary from the matching GitHub release, verifies its
checksum, and stores it under the package `lib/` directory. It also writes a
`natives.lock` file to your project root. Commit it so every environment
resolves the same native version. After upgrading the package, refresh the
library:

```bash
composer update avvertix/html-shot
vendor/bin/htmlshot update
```

## Your first image

The quickest way in is the `HtmlShot` façade. Give it some HTML and a few
options; it returns the encoded image bytes, which you can write to disk or
stream to a response.

```php
use HtmlShot\HtmlShot;

$png = HtmlShot::render(
    '<div style="display:flex; width:100%; height:100%; align-items:center;
                 justify-content:center; background:#ecfeff; color:#0f172a;
                 font-size:96px; font-weight:800;">That was fast!</div>',
    [
        'width'  => 1280,
        'height' => 630,
        'format' => 'png',
    ],
);

file_put_contents('hello.png', $png);
```

A few things to know up front:

- The single wrapper `<div>` is sized to fill the canvas with `width:100%;
  height:100%`. Layout is driven entirely by CSS, so reach for Flexbox or Grid
  to position things.
- Text only renders if a font covering it is available. The snippet above relies
  on a system fallback; to control typography, load your own font (see
  [Fonts & styles](fonts-and-styles.md)).

## Render options

All options are passed in the array argument to `HtmlShot::render()`:

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `width` | `int` | `1200` | Logical canvas width in pixels |
| `height` | `int` | `628` | Logical canvas height in pixels |
| `format` | `string` | `'png'` | `'png'`, `'webp'`, or `'jpeg'` |
| `quality` | `int` | `0` | 1–100 for the lossy formats; `0` uses the library default |
| `stylesheets` | `string[]` | `[]` | Extra CSS applied on top of the document |
| `fonts` | `Font[]` | `[]` | Fonts to load for this render |
| `devicePixelRatio` | `float` | `1.0` | Output scale factor (e.g. `2.0` for Retina) |

## Serving the bytes

`render()` returns raw image bytes, so you can stream them straight to a
response without touching the filesystem. For example, in plain PHP:

```php
header('Content-Type: image/png');
echo HtmlShot::render($html, ['width' => 1200, 'height' => 630]);
```

## Next steps

- [Fonts & styles](fonts-and-styles.md) — custom typography and the different
  ways to style your markup.
- [Advanced usage](advanced.md) — HiDPI output and reusing a `Context` +
  `Renderer` for repeated rendering.
- [`examples/`](../examples/) — runnable Open Graph and social-card scripts.
