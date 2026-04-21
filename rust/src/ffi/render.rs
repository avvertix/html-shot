//! FFI function for rendering HTML to an image.

use std::borrow::Cow;
use std::ffi::CStr;
use std::os::raw::c_char;

use takumi::layout::style::StyleSheet;
use takumi::layout::Viewport;
use takumi::rendering::{render, write_image, ImageOutputFormat};
use takumi::resources::image::{ImageSource, PersistentImageStore};

use super::context::context_ref;
use super::handles::{ContextHandle, OutputHandle};
use super::output::OutputData;
use crate::error::set_last_error;

/// Render an HTML string to an image and return an output handle.
///
/// - `ctx`: context holding loaded fonts; must remain valid until this call returns
/// - `html`: null-terminated HTML string
/// - `stylesheets` / `stylesheets_len`: array of CSS stylesheet strings to apply
/// - `width` / `height`: logical viewport dimensions in pixels
/// - `device_pixel_ratio`: output scale factor (1.0 = normal, 2.0 = HiDPI/2×).
///   The output bitmap will be `width * dpr` × `height * dpr` pixels while CSS
///   layout still happens at the logical dimensions.  Pass 0.0 to use 1.0.
/// - `format`: output format string "png" | "webp" | "jpeg" (null = "png")
/// - `quality`: JPEG/WebP quality 1–100 (0 = library default)
///
/// Returns an `OutputHandle` on success, or null on error.
/// The caller must free the handle with `takumi_output_free`.
#[no_mangle]
pub unsafe extern "C" fn takumi_render_html(
    ctx: *const ContextHandle,
    html: *const c_char,
    stylesheets: *const *const c_char,
    stylesheets_len: usize,
    width: u32,
    height: u32,
    device_pixel_ratio: f32,
    format: *const c_char,
    quality: u8,
) -> *mut OutputHandle {
    if ctx.is_null() || html.is_null() {
        set_last_error("null pointer: ctx or html");
        return std::ptr::null_mut();
    }

    let ctx_data = unsafe { context_ref(ctx) };
    let html_str = unsafe { CStr::from_ptr(html) }.to_string_lossy();

    // Parse HTML to a Takumi node tree; extract embedded <style> sheets
    let (root_node, extra_css) = crate::internal::html::parse_html(&html_str);

    // Collect caller-provided stylesheets
    let mut all_css: Vec<String> = Vec::new();
    if !stylesheets.is_null() {
        for i in 0..stylesheets_len {
            let ptr = unsafe { *stylesheets.add(i) };
            if !ptr.is_null() {
                all_css.push(unsafe { CStr::from_ptr(ptr) }.to_string_lossy().into_owned());
            }
        }
    }
    // Stylesheets extracted from the HTML follow user-provided ones
    all_css.extend(extra_css);

    // Normalize backslashes to forward slashes inside url(...) in all CSS.
    // Windows paths use `\` which the CSS parser treats as escape sequences
    // (e.g. `\a` → U+000A), making the parsed URL differ from the raw path.
    // Using `/` throughout avoids that mismatch.
    for sheet in &mut all_css {
        *sheet = normalize_url_backslashes(sheet);
    }

    // Pre-load any local image files referenced by <img src="..."> or CSS
    // url(...) into the persistent store so Takumi can resolve them.
    preload_local_images(&html_str, &all_css, &ctx_data.global.persistent_image_store);

    let stylesheet = StyleSheet::parse_list_loosy(all_css.iter().map(|s| s.as_str()));

    // Resolve output format
    let fmt_str = if format.is_null() {
        "png"
    } else {
        unsafe { CStr::from_ptr(format) }.to_str().unwrap_or("png")
    };
    let output_format = match fmt_str.to_ascii_lowercase().as_str() {
        "webp" => ImageOutputFormat::WebP,
        "jpeg" | "jpg" => ImageOutputFormat::Jpeg,
        "ico" => ImageOutputFormat::Ico,
        _ => ImageOutputFormat::Png,
    };

    // Render
    let dpr = if device_pixel_ratio > 0.0 { device_pixel_ratio } else { 1.0 };
    let render_options = takumi::rendering::RenderOptions::builder()
        .viewport(Viewport::new((width, height)).with_device_pixel_ratio(dpr))
        .node(root_node)
        .global(&ctx_data.global)
        .stylesheet(stylesheet)
        .build();

    let image = match render(render_options) {
        Ok(img) => img,
        Err(e) => {
            set_last_error(format!("render error: {e}"));
            return std::ptr::null_mut();
        }
    };

    // Encode to bytes
    let quality_opt = if quality == 0 { None } else { Some(quality) };
    let mut bytes: Vec<u8> = Vec::new();
    if let Err(e) = write_image(Cow::Owned(image), &mut bytes, output_format, quality_opt) {
        set_last_error(format!("encode error: {e}"));
        return std::ptr::null_mut();
    }

    Box::into_raw(Box::new(OutputData { bytes })) as *mut OutputHandle
}

/// Scan `html` (for `<img src>` and inline `style` attributes) and `css_sheets`
/// (for `background-image: url(...)` rules) for local file paths, read those
/// files from disk, and cache the decoded images in `store`.
///
/// Skips `http://`, `https://`, and `data:` entries — Takumi handles those.
/// Already-cached entries are skipped to avoid redundant I/O on context reuse.
fn preload_local_images(html: &str, css_sheets: &[String], store: &PersistentImageStore) {
    let fragment = scraper::Html::parse_fragment(html);

    // ── 1. <img src="..."> ──────────────────────────────────────────────────
    if let Ok(sel) = scraper::Selector::parse("img[src]") {
        for el in fragment.select(&sel) {
            if let Some(src) = el.attr("src") {
                try_preload(src, store);
            }
        }
    }

    // ── 2. All CSS (inline-style rules wrapped into extra_css by html.rs,
    //        plus <style> block content and caller-provided stylesheets).
    //        These have already been normalised (backslashes → forward slashes).
    for sheet in css_sheets {
        for url in css_url_paths(sheet) {
            try_preload(&url, store);
        }
    }
}

/// Try to read `path` from disk and insert it into `store`.
/// Does nothing if `path` is a remote URL, data URI, or already cached.
fn try_preload(path: &str, store: &PersistentImageStore) {
    if path.starts_with("http://") || path.starts_with("https://") || path.starts_with("data:") {
        return;
    }
    if store.get(path).is_some() {
        return;
    }
    let Ok(bytes) = std::fs::read(path) else {
        return;
    };
    let Ok(image_source) = ImageSource::from_bytes(&bytes) else {
        return;
    };
    store.insert(path.to_string(), image_source);
}

/// Replace `\` with `/` inside every `url(...)` token in `css`.
///
/// The CSS tokeniser treats `\` as an escape character (e.g. `\a` → U+000A),
/// so Windows paths passed verbatim would be decoded incorrectly.  Forward
/// slashes are safe in Windows paths and pass through CSS parsing unchanged.
fn normalize_url_backslashes(css: &str) -> String {
    if !css.contains("url(") || !css.contains('\\') {
        return css.to_string();
    }
    let mut out = String::with_capacity(css.len());
    let mut rest = css;
    while let Some(pos) = rest.find("url(") {
        out.push_str(&rest[..pos + 4]); // everything up to and including "url("
        rest = &rest[pos + 4..];
        // Find the extent of the url(...) content and normalise its backslashes
        let close = url_token_end(rest);
        out.push_str(&rest[..close].replace('\\', "/"));
        rest = &rest[close..];
    }
    out.push_str(rest);
    out
}

/// Return the byte index of the character *after* the `url(` content ends.
/// Handles quoted and unquoted forms; stops at `)` for unquoted values.
fn url_token_end(s: &str) -> usize {
    let trimmed_start = s.len() - s.trim_start_matches(|c: char| c.is_ascii_whitespace()).len();
    let inner = &s[trimmed_start..];
    if let Some(first) = inner.chars().next() {
        if first == '"' || first == '\'' {
            // quoted: advance past opening quote, find closing quote, then ')'
            let content = &inner[1..];
            let close_q = content.find(first).unwrap_or(content.len());
            let after_q = trimmed_start + 1 + close_q;
            // include the closing quote and any trailing whitespace + ')'
            let remainder = &s[after_q..];
            let close_paren = remainder.find(')').map(|i| i + 1).unwrap_or(remainder.len());
            return after_q + close_paren;
        }
    }
    // unquoted: find ')'
    s.find(')').map(|i| i + 1).unwrap_or(s.len())
}

/// Extract the inner URL strings from all `url(...)` occurrences in `css`.
///
/// Handles quoted (`url('...')`, `url("...")`) and unquoted (`url(...)`) forms.
fn css_url_paths(css: &str) -> Vec<String> {
    let mut results = Vec::new();
    let mut rest = css;
    while let Some(pos) = rest.find("url(") {
        rest = &rest[pos + 4..]; // skip "url("
        let inner = rest.trim_start_matches(|c: char| c.is_ascii_whitespace());
        let (url, after) = if inner.starts_with('"') {
            let content = &inner[1..];
            let end = content.find('"').unwrap_or(content.len());
            (&content[..end], &content[end..])
        } else if inner.starts_with('\'') {
            let content = &inner[1..];
            let end = content.find('\'').unwrap_or(content.len());
            (&content[..end], &content[end..])
        } else {
            let end = inner
                .find(|c: char| c == ')' || c.is_ascii_whitespace())
                .unwrap_or(inner.len());
            (&inner[..end], &inner[end..])
        };
        if !url.is_empty() {
            results.push(url.to_string());
        }
        rest = after;
    }
    results
}
