# Fonts & styles

## Custom fonts

`html-shot` ships no fonts. Any text you render needs a font that covers it, so
load your own with the `Font` helper and pass them in the `fonts` option. The
`family` you give here is the name you then reference from CSS `font-family`.

```php
use HtmlShot\Font;
use HtmlShot\HtmlShot;

$png = HtmlShot::render(
    '<div style="font-family: Inter; font-size: 64px; font-weight: 700;">Hello</div>',
    [
        'width'  => 1200,
        'height' => 630,
        'fonts'  => [
            Font::fromFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400),
            Font::fromFile('/fonts/Inter-Bold.ttf',    family: 'Inter', weight: 700),
        ],
    ],
);
```

Load one descriptor per weight/style you intend to use, all under the same
`family`; the engine picks the right file based on the CSS `font-weight` and
`font-style` at render time.

### From a file or from bytes

```php
// From a path on disk
Font::fromFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400);

// From raw bytes, handy when the font lives in a database, cache, or archive
Font::fromData(file_get_contents('/fonts/Inter-Regular.ttf'), family: 'Inter', weight: 400);
```

Both accept `family`, `weight`, and `style` overrides:

| Argument | Default | Notes |
| --- | --- | --- |
| `family` | `''` | Override the family name; empty auto-detects from the font metadata |
| `weight` | `0` | Override weight 1–1000; `0` auto-detects |
| `style` | `''` | `'normal'`, `'italic'`, or `'oblique'`; empty auto-detects |

Supported file formats are TTF, OTF, WOFF, and WOFF2.

### Variable fonts

A single variable font file covers a range of weights. Register it once and use
any weight from its axis. The examples in this repo do exactly that with Geist:

```php
Font::fromFile(__DIR__.'/tests/fonts/Geist/variable/Geist[wght].ttf', family: 'Geist');
```

```html
<h1 style="font-family: Geist; font-weight: 800;">Bold</h1>
<p  style="font-family: Geist; font-weight: 400;">Regular</p>
```

### Multiple families in one render

Register as many families as you need and switch between them in CSS:

```php
'fonts' => [
    Font::fromFile($base.'/Geist/variable/Geist[wght].ttf',         family: 'Geist'),
    Font::fromFile($base.'/GeistMono/variable/GeistMono[wght].ttf', family: 'Geist Mono'),
],
```

```html
<span style="font-family: 'Geist Mono';">vercel/</span>
<span style="font-family: Geist;">satori</span>
```

## Styling your markup

Layout and appearance are driven entirely by CSS. There are four ways to apply
it, and they can be combined.

### 1. Inline `style` attributes

The most direct option, good for self-contained snippets:

```html
<div style="display:flex; align-items:center; justify-content:center;
            width:100%; height:100%; background:#09090b; color:#fff;">
  Hello
</div>
```

### 2. `<style>` blocks

A `<style>` tag inside the document is pulled out and applied as a stylesheet,
so class selectors work as expected:

```html
<style>
  .card { display:flex; width:100%; height:100%; background:#0b1020; color:#fff; }
</style>
<div class="card">Hello</div>
```

### 3. External stylesheets (`stylesheets` option)

Keep styling out of the markup entirely by passing CSS through the
`stylesheets` option. This works well when your CSS lives in its own file: the
HTML carries only semantic class names.

```php
$css = file_get_contents(__DIR__.'/styles.css');

$png = HtmlShot::render('<div class="card">Hello</div>', [
    'width'       => 1200,
    'height'      => 630,
    'stylesheets' => [$css], // pass any number of sheets
]);
```

The array can hold several sheets; they are applied on top of the document just
like linked stylesheets in a browser. See the
[`event-card`](../examples/event-card/) example for a complete walkthrough.

### 4. `tw` Tailwind utilities

Takumi's engine understands a `tw` attribute carrying Tailwind utility classes,
so you can lay out a card without writing any CSS:

```html
<div tw="flex h-full w-full flex-col justify-end bg-[#16130f] p-20">
  <h1 tw="m-0 text-8xl font-bold tracking-tighter text-white">HtmlShot.</h1>
  <p  tw="mt-10 text-3xl text-[#a8a29a]">html in, image out</p>
</div>
```

Arbitrary values (`bg-[#16130f]`, `text-[12em]`) are supported. The
[`tailwind`](../examples/tailwind/) and [`stars`](../examples/stars/) examples
use this approach.

## Images and SVG

Both `<img src="...">` and CSS `background-image: url(...)` accept local file
paths and `data:` URIs; `http(s)` URLs are passed through to the engine. Inline
`<svg>` is rasterized as an image, so vector graphics render without a separate
conversion step. The [main README's "Deep Dive"](../README.md#deep-dive) covers
the exact handling.

## Next steps

- [Advanced usage](advanced.md) — HiDPI output and reusing a `Context` +
  `Renderer` for repeated rendering.
