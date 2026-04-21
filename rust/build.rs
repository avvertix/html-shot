use std::env;
use std::path::PathBuf;

fn main() {
    let crate_dir = env::var("CARGO_MANIFEST_DIR").unwrap();
    let config_file = PathBuf::from(&crate_dir).join("cbindgen.toml");
    let output_file = PathBuf::from(&crate_dir)
        .join("..")
        .join("include")
        .join("takumi_php.h");

    let include_dir = output_file.parent().unwrap();
    std::fs::create_dir_all(include_dir).ok();

    if config_file.exists() {
        cbindgen::Builder::new()
            .with_crate(&crate_dir)
            .with_config(cbindgen::Config::from_file(&config_file).unwrap())
            .generate()
            .expect("Unable to generate bindings")
            .write_to_file(&output_file);
    } else {
        cbindgen::Builder::new()
            .with_crate(&crate_dir)
            .with_language(cbindgen::Language::C)
            .generate()
            .expect("Unable to generate bindings")
            .write_to_file(&output_file);
    }

    let pkg_version = env!("CARGO_PKG_VERSION");
    println!("cargo:rustc-env=TAKUMI_PHP_VERSION={pkg_version}");

    println!("cargo:rerun-if-changed=src/");
    println!("cargo:rerun-if-changed=cbindgen.toml");
}
