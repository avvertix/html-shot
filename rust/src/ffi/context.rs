//! FFI functions for the rendering context (GlobalContext + font loading).

use std::ffi::CStr;
use std::os::raw::{c_char, c_int};

use parley::fontique::FontInfoOverride;
use parley::{FontStyle, FontWeight};
use takumi::{resources::font::FontResource, GlobalContext};

use super::handles::ContextHandle;
use crate::error::set_last_error;

pub(crate) struct ContextData {
    pub(crate) global: GlobalContext,
}

pub(crate) unsafe fn context_ref(h: *const ContextHandle) -> &'static ContextData {
    &*(h as *const ContextData)
}

pub(crate) unsafe fn context_mut(h: *mut ContextHandle) -> &'static mut ContextData {
    &mut *(h as *mut ContextData)
}

/// Create a new rendering context.
///
/// The returned handle must be freed with `takumi_context_free`.
/// Reuse a context across multiple render calls to share font caches.
#[no_mangle]
pub unsafe extern "C" fn takumi_context_new() -> *mut ContextHandle {
    let data = ContextData {
        global: GlobalContext::default(),
    };
    Box::into_raw(Box::new(data)) as *mut ContextHandle
}

/// Free a context handle and release all associated resources.
#[no_mangle]
pub unsafe extern "C" fn takumi_context_free(handle: *mut ContextHandle) {
    if !handle.is_null() {
        drop(unsafe { Box::from_raw(handle as *mut ContextData) });
    }
}

/// Load a font from raw bytes into the context.
///
/// - `data` / `data_len`: font file bytes (TTF, OTF, WOFF, WOFF2)
/// - `family`: override family name (null = auto-detect from font metadata)
/// - `weight`: override weight in 1–1000 (0 = auto-detect)
/// - `style`: override style string "normal" | "italic" | "oblique" (null = auto-detect)
///
/// Returns 0 on success, -1 on error (call `takumi_get_last_error` for details).
#[no_mangle]
pub unsafe extern "C" fn takumi_context_load_font_data(
    handle: *mut ContextHandle,
    data: *const u8,
    data_len: usize,
    family: *const c_char,
    weight: c_int,
    style: *const c_char,
) -> c_int {
    if handle.is_null() || data.is_null() {
        set_last_error("null pointer");
        return -1;
    }
    let ctx = unsafe { context_mut(handle) };
    let bytes = unsafe { std::slice::from_raw_parts(data, data_len) };

    let family_owned = nullable_cstr_to_string(family);
    let style_owned = nullable_cstr_to_string(style);

    load_font_bytes(ctx, bytes, family_owned.as_deref(), weight, style_owned.as_deref())
}

/// Load a font from a file path into the context.
///
/// - `path`: null-terminated path to the font file
/// - `family` / `weight` / `style`: same semantics as `takumi_context_load_font_data`
///
/// Returns 0 on success, -1 on error.
#[no_mangle]
pub unsafe extern "C" fn takumi_context_load_font_file(
    handle: *mut ContextHandle,
    path: *const c_char,
    family: *const c_char,
    weight: c_int,
    style: *const c_char,
) -> c_int {
    if handle.is_null() || path.is_null() {
        set_last_error("null pointer");
        return -1;
    }
    let ctx = unsafe { context_mut(handle) };
    let path_str = unsafe { CStr::from_ptr(path) }.to_string_lossy();

    let bytes = match std::fs::read(path_str.as_ref()) {
        Ok(b) => b,
        Err(e) => {
            set_last_error(format!("Failed to read font file '{}': {e}", path_str));
            return -1;
        }
    };

    let family_owned = nullable_cstr_to_string(family);
    let style_owned = nullable_cstr_to_string(style);

    load_font_bytes(ctx, &bytes, family_owned.as_deref(), weight, style_owned.as_deref())
}

fn load_font_bytes(
    ctx: &mut ContextData,
    bytes: &[u8],
    family: Option<&str>,
    weight: c_int,
    style: Option<&str>,
) -> c_int {
    let font_style = style.map(|s| match s.to_ascii_lowercase().as_str() {
        "italic" => FontStyle::Italic,
        _ => FontStyle::Normal,
    });

    let font_weight = if weight > 0 {
        Some(FontWeight::new(weight as f32))
    } else {
        None
    };

    let resource = FontResource::new(bytes).override_info(FontInfoOverride {
        family_name: family,
        width: None,
        style: font_style,
        weight: font_weight,
        axes: None,
    });

    match ctx.global.font_context.load_and_store(resource) {
        Ok(()) => 0,
        Err(e) => {
            set_last_error(format!("{e}"));
            -1
        }
    }
}

fn nullable_cstr_to_string(ptr: *const c_char) -> Option<String> {
    if ptr.is_null() {
        None
    } else {
        Some(unsafe { CStr::from_ptr(ptr) }.to_string_lossy().into_owned())
    }
}
