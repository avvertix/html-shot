//! Opaque handle types exposed to PHP via cbindgen.
//!
//! Each handle is a zero-size type used as a type-safe C pointer.
//! Rust functions cast from the opaque handle pointer to the actual internal data type.

/// Opaque handle for a `GlobalContext` (font cache + persistent image store).
pub struct ContextHandle {
    _private: [u8; 0],
}

/// Opaque handle for rendered image bytes ready to be read by PHP.
pub struct OutputHandle {
    _private: [u8; 0],
}
