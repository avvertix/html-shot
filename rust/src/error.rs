//! Thread-local last-error storage for FFI error reporting.

use std::cell::RefCell;
use std::ffi::CString;
use std::os::raw::c_char;

thread_local! {
    static LAST_ERROR: RefCell<Option<CString>> = const { RefCell::new(None) };
}

/// Store an error message in thread-local storage.
pub fn set_last_error(msg: impl AsRef<str>) {
    let s = msg.as_ref();
    let cstr = CString::new(s)
        .unwrap_or_else(|_| CString::new("(error message contained null byte)").unwrap());
    LAST_ERROR.with(|e| *e.borrow_mut() = Some(cstr));
}

/// Clear the last error.
pub fn clear_last_error() {
    LAST_ERROR.with(|e| *e.borrow_mut() = None);
}

/// Return a pointer to the last error message, or null if none.
///
/// The pointer is valid until the next call that sets an error.
#[no_mangle]
pub extern "C" fn takumi_get_last_error() -> *const c_char {
    LAST_ERROR.with(|e| {
        e.borrow()
            .as_ref()
            .map(|s| s.as_ptr())
            .unwrap_or(std::ptr::null())
    })
}

/// Clear the last error message.
#[no_mangle]
pub extern "C" fn takumi_clear_last_error() {
    clear_last_error();
}
