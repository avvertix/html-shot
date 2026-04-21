//! FFI functions for the rendered image output handle.

use super::handles::OutputHandle;
use crate::error::set_last_error;
use std::ffi::CStr;
use std::os::raw::c_char;

pub(crate) struct OutputData {
    pub bytes: Vec<u8>,
}

pub(crate) unsafe fn output_ref(h: *mut OutputHandle) -> &'static OutputData {
    &*(h as *const OutputData)
}

/// Return a pointer to the rendered image bytes and write the length to `*out_len`.
///
/// The pointer is valid until the handle is freed with `takumi_output_free`.
#[no_mangle]
pub unsafe extern "C" fn takumi_output_bytes(
    handle: *mut OutputHandle,
    out_len: *mut usize,
) -> *const u8 {
    if handle.is_null() {
        if !out_len.is_null() {
            unsafe { *out_len = 0 };
        }
        return std::ptr::null();
    }
    let d = unsafe { output_ref(handle) };
    if !out_len.is_null() {
        unsafe { *out_len = d.bytes.len() };
    }
    d.bytes.as_ptr()
}

/// Return the byte length of the rendered image.
#[no_mangle]
#[allow(clippy::cast_possible_wrap)]
pub unsafe extern "C" fn takumi_output_size(handle: *mut OutputHandle) -> i64 {
    if handle.is_null() {
        return 0;
    }
    unsafe { output_ref(handle) }.bytes.len() as i64
}

/// Save the rendered image to a file path. Returns 0 on success, -1 on error.
#[no_mangle]
pub unsafe extern "C" fn takumi_output_save(handle: *mut OutputHandle, path: *const c_char) -> i32 {
    if handle.is_null() || path.is_null() {
        set_last_error("null pointer");
        return -1;
    }
    let d = unsafe { output_ref(handle) };
    let path_str = unsafe { CStr::from_ptr(path) }.to_string_lossy();
    std::fs::write(path_str.as_ref(), &d.bytes)
        .map(|_| 0)
        .unwrap_or_else(|e| {
            set_last_error(format!("Failed to write image to '{}': {e}", path_str));
            -1
        })
}

/// Free the output handle and its associated memory.
#[no_mangle]
pub unsafe extern "C" fn takumi_output_free(handle: *mut OutputHandle) {
    if !handle.is_null() {
        drop(unsafe { Box::from_raw(handle as *mut OutputData) });
    }
}
