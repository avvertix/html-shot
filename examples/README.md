# Examples

Here a few examples on how to render images with `html-shot`. Each one is a small,
self-contained recipe: read the source to see the technique, run it to get the
PNG in that example's `output/` folder.


| | | |
| :---: | :---: | :---: |
| <img src="simple/output/simple.png" width="240"><br>`simple` | <img src="tailwind/output/tailwind.png" width="240"><br>`tailwind` |  <img src="event/output/event.png" width="240"><br>`event` |
| <img src="changelog/output/changelog.png" width="240"><br>`changelog` | <img src="quote/output/quote.png" width="240"><br>`quote` | <img src="repository/output/repository.png" width="240"><br>`repository` |
| <img src="stars/output/stars.png" width="240"><br>`stars` | <img src="event-card/output/event-card.png" width="240"><br>`event-card` | <img src="line-chart/output/line-chart.png" width="240"><br>`line-chart` |
| <img src="banner/output/banner.png" width="240"><br>`banner` | | |

## What each example teaches

| Folder | What you'll learn |
| --- | --- |
| `simple/` | The **`HtmlShot::render()` façade**, inline CSS, loading a single font |
| `tailwind/` | Styling with the **`tw` Tailwind** utility attribute instead of CSS |
| `htmlshot-promo/` | Combining `tw` classes with a local PNG via `<img src>` |
| `stars/` | Absolute positioning and a full-bleed background image with `tw` |
| `changelog/` | Building repeated rows from a PHP array, uppercase tag labels |
| `event/` | A full-height flex column split into three regions, letter-spacing |
| `quote/` | Large decorative typography and an inline metadata row |
| `repository/` | Mixing **two font families** (Geist + Geist Mono) in one render |
| `event-card/` | Passing a **custom stylesheet** via the `stylesheets` option — markup carries only class names, all styling lives in `styles.css` |
| `line-chart/` | Generating an **inline `<svg>`** from a data array (computed points, area fill, grid) with HTML axis labels laid over the same coordinate space |
| `banner/` | Reusing a `Context` + `Renderer`, emitting multiple outputs (1× PNG and a 2× WebP via `devicePixelRatio`), an inline SVG watermark |


## Running them

```bash
# from the project root
php examples/simple/simple.php
```

Every example writes to its own `output/` directory and prints the file it
saved. To rebuild them all at once:

```bash
php examples/regenerate.php
```

**Prerequisites**

- `composer install`
- `vendor/bin/htmlshot install` to download the native rendering library
- The bundled fonts under `tests/fonts/` (Geist, Geist Mono) — most examples
  load one or both.

