# Advanced usage

## HiDPI / Retina output

`width` and `height` describe the logical canvas: the coordinate space your CSS
lays out in. The `devicePixelRatio` option scales the physical bitmap without
touching that layout, so a 1200×630 layout at `devicePixelRatio: 2.0` produces a
2400×1260 image, pixel-doubled and crisp on Retina displays.

```php
use HtmlShot\HtmlShot;

// 1× — 1200×630 physical pixels
$png = HtmlShot::render($html, [
    'width'  => 1200,
    'height' => 630,
    'format' => 'png',
]);

// 2× — same layout, 2400×1260 physical pixels
$retina = HtmlShot::render($html, [
    'width'            => 1200,
    'height'           => 630,
    'format'           => 'webp',
    'devicePixelRatio' => 2.0,
]);
```

Because the layout is unchanged, font sizes, padding, and positions stay
identical between the 1× and 2× renders; only the output resolution differs.
This is the usual way to ship both a normal and an `@2x` asset from one
template. The [`banner`](../examples/banner/banner.php) example emits both.

### Choosing a format and quality

`format` accepts `'png'`, `'webp'`, or `'jpeg'`. For the lossy formats, set
`quality` from 1–100 (`0` uses the library default):

```php
$jpeg = HtmlShot::render($html, [
    'width'   => 1200,
    'height'  => 630,
    'format'  => 'jpeg',
    'quality' => 82,
]);
```

PNG is lossless and ignores `quality`. WebP at a high quality is usually the
best size/clarity trade-off for HiDPI assets.

## Using Context and Renderer directly

The `HtmlShot` façade builds a fresh `Context` and reloads every font on each
call. When you render repeatedly (a batch of cards, or an HTTP endpoint serving
many images), build the `Context` once, load the fonts a single time, and drive
a `Renderer` directly.

```php
use HtmlShot\Context;
use HtmlShot\Renderer;

$context = new Context;
$context->loadFontFile('/fonts/Inter-Regular.ttf', family: 'Inter', weight: 400);
$context->loadFontFile('/fonts/Inter-Bold.ttf',    family: 'Inter', weight: 700);

$renderer = new Renderer($context);

foreach ($posts as $post) {
    $html = renderTemplate($post); // your own HTML builder

    // 1× PNG
    file_put_contents("{$post->slug}.png", $renderer->render($html, 1200, 630));

    // 2× WebP for HiDPI displays
    $webp = $renderer->render($html, 1200, 630, 'webp', devicePixelRatio: 2.0);
    file_put_contents("{$post->slug}@2x.webp", $webp);
}
```

The fonts are parsed once and reused across every `render()` call, which is the
main reason to prefer this path over the façade in a loop or a long-running
process.

### `Renderer::render()` signature

```php
$renderer->render(
    string $html,
    int    $width = 1200,
    int    $height = 628,
    string $format = 'png',
    int    $quality = 0,
    array  $stylesheets = [],
    float  $devicePixelRatio = 1.0,
    float  $baseFontSize = 16.0,
): string;
```

Using named arguments keeps calls readable when you only need a couple of the
options:

```php
$bytes = $renderer->render(
    $html,
    width: 1200,
    height: 630,
    stylesheets: [$css],
    devicePixelRatio: 2.0,
);
```

`baseFontSize` is the root font size in pixels used to resolve `rem` and the
initial `em`. It's the equivalent of the browser's `<html>` font-size, 16px by
default. Raise it to scale a whole `rem`-based template at once.

### Loading fonts onto a Context

A `Context` exposes the same loaders as the `Font` helper:

```php
// From a file
$context->loadFontFile('/fonts/Inter-Bold.ttf', family: 'Inter', weight: 700);

// From raw bytes
$context->loadFontData(file_get_contents('/fonts/Inter-Bold.ttf'), family: 'Inter', weight: 700);
```

You can also build `Font` descriptors and load them in:

```php
use HtmlShot\Font;

Font::fromFile('/fonts/Inter-Bold.ttf', family: 'Inter', weight: 700)->loadInto($context);
```

## Error handling

Render and font-loading failures throw `HtmlShot\Exception\RuntimeException`.
Wrap calls that depend on external input (user-supplied HTML, fonts read from
disk) so a single bad render doesn't take down the request:

```php
use HtmlShot\Exception\RuntimeException;

try {
    $png = $renderer->render($html, 1200, 630);
} catch (RuntimeException $e) {
    // log and fall back to a static placeholder
}
```

## See also

- [Getting started](getting-started.md)
- [Fonts & styles](fonts-and-styles.md)
- [`examples/`](../examples/)