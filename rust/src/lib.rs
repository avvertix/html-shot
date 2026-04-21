//! takumi_php — Rust FFI library for PHP Takumi integration.
//!
//! Exposes C-compatible functions that let PHP render HTML to images
//! using the Takumi layout/rendering engine, via `ext-ffi`.

pub mod error;
pub mod ffi;
pub mod internal;
