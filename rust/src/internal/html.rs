//! HTML-to-Takumi-Node converter.
//!
//! Mirrors the behaviour of `takumi-helpers`' `fromStaticMarkup`:
//! - `<style>` tags are extracted as CSS stylesheet strings
//! - Inline `style="..."` attributes are promoted to generated class rules
//! - `<img>` elements become `Node::image`
//! - Text content becomes `Node::text`
//! - Everything else becomes `Node::container` with class/id/tag metadata
//! - `<html>`, `<head>`, and `<body>` wrappers are treated as transparent

use scraper::node::Node as HtmlNode;
use scraper::Html;
use takumi::layout::node::Node;

/// Parse `html` and return a root `Node` plus any extra CSS strings.
///
/// Extra CSS comes from `<style>` tags and promoted inline styles.
pub fn parse_html(html: &str) -> (Node, Vec<String>) {
    let fragment = Html::parse_fragment(html);
    let mut extra_css: Vec<String> = Vec::new();
    let mut counter = 0usize;

    // html5ever always wraps fragments in <html><head/><body>…</body></html>.
    // Locate the <body> element and start from its children.
    let body_selector = scraper::Selector::parse("body").expect("valid selector");
    let mut children: Vec<Node> = Vec::new();

    if let Some(body) = fragment.select(&body_selector).next() {
        for child in body.children() {
            if let Some(n) = convert_node(child, &mut extra_css, &mut counter) {
                children.push(n);
            }
        }
    } else {
        for child in fragment.tree.root().children() {
            if let Some(n) = convert_node(child, &mut extra_css, &mut counter) {
                children.push(n);
            }
        }
    }

    let root = match children.len() {
        0 => Node::container(vec![]),
        1 => children.remove(0),
        _ => Node::container(children),
    };

    (root, extra_css)
}

type HtmlNodeRef<'a> = ego_tree::NodeRef<'a, HtmlNode>;

fn convert_node(
    node_ref: HtmlNodeRef<'_>,
    extra_css: &mut Vec<String>,
    counter: &mut usize,
) -> Option<Node> {
    match node_ref.value() {
        HtmlNode::Text(text) => {
            let content = text.text.trim();
            if content.is_empty() {
                return None;
            }
            Some(Node::text(content.to_string()))
        }

        HtmlNode::Element(element) => {
            let tag = element.name();

            // Transparent structural elements — pass through children
            if tag == "head" {
                return None;
            }
            if matches!(tag, "html" | "body") {
                let children: Vec<Node> = node_ref
                    .children()
                    .filter_map(|c| convert_node(c, extra_css, counter))
                    .collect();
                return if children.is_empty() {
                    None
                } else if children.len() == 1 {
                    let v = children;
                    v.into_iter().next()
                } else {
                    Some(Node::container(children))
                };
            }

            // <style> — extract CSS content
            if tag == "style" {
                let mut css = String::new();
                for child in node_ref.children() {
                    if let HtmlNode::Text(t) = child.value() {
                        css.push_str(&t.text);
                    }
                }
                if !css.is_empty() {
                    extra_css.push(css);
                }
                return None;
            }

            // <br> — newline text node
            if tag == "br" {
                return Some(Node::text("\n".to_string()));
            }

            // <svg> — serialize the whole subtree back to SVG markup and treat it
            // as an image source so resvg can rasterize it.
            if tag == "svg" {
                if let Some(el) = scraper::ElementRef::wrap(node_ref) {
                    let mut svg_markup = el.html();
                    // html5ever may drop the xmlns attribute when serializing SVG
                    // in an HTML context; takumi's is_svg_like() requires it.
                    if !svg_markup.contains("xmlns") {
                        svg_markup = svg_markup
                            .replacen("<svg", r#"<svg xmlns="http://www.w3.org/2000/svg""#, 1);
                    }
                    let node = match (
                        element.attr("width").and_then(|w| w.parse::<f32>().ok()),
                        element.attr("height").and_then(|h| h.parse::<f32>().ok()),
                    ) {
                        (Some(w), Some(h)) => Node::image((svg_markup.as_str(), w, h)),
                        _ => Node::image(svg_markup.as_str()),
                    };
                    let node = apply_metadata(node, element, extra_css, counter);
                    return Some(node);
                }
                return None;
            }

            // <img> — image node
            if tag == "img" {
                let src = element.attr("src").unwrap_or("").to_string();
                let node = match (
                    element.attr("width").and_then(|w| w.parse::<f32>().ok()),
                    element.attr("height").and_then(|h| h.parse::<f32>().ok()),
                ) {
                    (Some(w), Some(h)) => Node::image((src.as_str(), w, h)),
                    _ => Node::image(src.as_str()),
                };
                let node = apply_metadata(node, &element, extra_css, counter);
                return Some(node);
            }

            // All other elements → container
            let children: Vec<Node> = node_ref
                .children()
                .filter_map(|c| convert_node(c, extra_css, counter))
                .collect();

            let node = Node::container(children).with_tag_name(tag);
            let node = apply_metadata(node, element, extra_css, counter);
            Some(node)
        }

        _ => None,
    }
}

/// Apply class, id, and inline-style metadata to a node.
fn apply_metadata(
    mut node: Node,
    element: &scraper::node::Element,
    extra_css: &mut Vec<String>,
    counter: &mut usize,
) -> Node {
    let existing_class = element.attr("class");

    // Promote inline style="…" to a generated CSS class
    let class_name: Option<String> = if let Some(inline) = element.attr("style") {
        *counter += 1;
        let cls = format!("_tk_inline_{}", *counter);
        extra_css.push(format!(".{cls} {{ {inline} }}"));
        Some(match existing_class {
            Some(c) => format!("{c} {cls}"),
            None => cls,
        })
    } else {
        existing_class.map(|s| s.to_string())
    };

    if let Some(cls) = class_name {
        node = node.with_class_name(cls);
    }
    if let Some(id) = element.attr("id") {
        node = node.with_id(id);
    }
    node
}

#[cfg(test)]
mod tests {
    use super::*;
    use takumi::GlobalContext;
    use takumi::layout::Viewport;
    use takumi::layout::style::StyleSheet;
    use takumi::rendering::{render, RenderOptions};

    /// Render an HTML string at 200×200 and return the RgbaImage.
    fn render_html(html: &str) -> ::image::RgbaImage {
        let (root_node, extra_css) = parse_html(html);
        let stylesheet = StyleSheet::parse_list_loosy(extra_css.iter().map(|s| s.as_str()));
        let global = GlobalContext::default();

        let options = RenderOptions::builder()
            .viewport(Viewport::new((200u32, 200u32)))
            .node(root_node)
            .global(&global)
            .stylesheet(stylesheet)
            .build();

        render(options).expect("render should succeed")
    }

    #[test]
    fn inline_style_red_background_renders_opaque() {
        let image = render_html(
            r#"<div style="background-color: red; width: 100px; height: 100px;"></div>"#,
        );
        let p = image.get_pixel(50, 50);
        assert!(
            p[3] > 0,
            "expected opaque pixel at (50,50), got transparent; RGBA={p:?}"
        );
        assert!(
            p[0] > 200,
            "expected red pixel at (50,50); RGBA={p:?}"
        );
    }

    #[test]
    fn style_tag_green_background_renders_opaque() {
        let image = render_html(
            r#"<style>.box { background-color: green; width: 80px; height: 80px; }</style>
               <div class="box"></div>"#,
        );
        let p = image.get_pixel(40, 40);
        assert!(
            p[3] > 0,
            "expected opaque pixel at (40,40), got transparent; RGBA={p:?}"
        );
        assert!(
            p[1] > p[0],
            "expected greenish pixel at (40,40); RGBA={p:?}"
        );
    }

    #[test]
    fn parse_html_inline_style_produces_css_class() {
        let (_node, css) = parse_html(r#"<div style="color: black"></div>"#);
        assert_eq!(css.len(), 1, "expected one CSS rule, got: {css:?}");
        assert!(
            css[0].contains("color"),
            "CSS rule doesn't contain 'color': {}",
            css[0]
        );
        assert!(
            css[0].contains("_tk_inline_"),
            "CSS rule doesn't reference expected class: {}",
            css[0]
        );
    }

    #[test]
    fn inline_svg_renders_opaque() {
        // A red square drawn as inline SVG — should produce non-transparent pixels.
        let image = render_html(
            r#"<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
                 <rect width="200" height="200" fill="red"/>
               </svg>"#,
        );
        let p = image.get_pixel(100, 100);
        assert!(
            p[3] > 0,
            "expected opaque pixel at (100,100) from inline SVG, got transparent; RGBA={p:?}"
        );
        assert!(
            p[0] > 200,
            "expected red pixel at (100,100) from inline SVG; RGBA={p:?}"
        );
    }

    #[test]
    fn inline_svg_respects_width_height_attributes() {
        // SVG is 50×50 but placed in a 200×200 canvas — pixel at (150,150) must be
        // transparent, proving the image node was sized to 50×50 and not stretched.
        let image = render_html(
            r#"<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50">
                 <rect width="50" height="50" fill="blue"/>
               </svg>"#,
        );
        let inside = image.get_pixel(25, 25);
        assert!(inside[3] > 0, "expected opaque pixel inside the SVG at (25,25); RGBA={inside:?}");
        assert!(inside[2] > 150, "expected blue pixel inside the SVG at (25,25); RGBA={inside:?}");

        let outside = image.get_pixel(150, 150);
        assert!(
            outside[3] == 0,
            "expected transparent pixel outside the 50×50 SVG at (150,150); RGBA={outside:?}"
        );
    }

    /// Diagnostic: print what the scraper tree looks like for a simple fragment.
    #[test]
    fn diagnose_fragment_tree_structure() {
        let html = r#"<div style="color: black">hello</div>"#;
        let fragment = scraper::Html::parse_fragment(html);

        // Walk the whole tree and print every node
        fn walk(node: ego_tree::NodeRef<scraper::node::Node>, depth: usize) {
            let indent = "  ".repeat(depth);
            match node.value() {
                scraper::node::Node::Document => eprintln!("{indent}[Document]"),
                scraper::node::Node::Fragment => eprintln!("{indent}[Fragment]"),
                scraper::node::Node::Doctype(d) => eprintln!("{indent}[Doctype: {}]", d.name),
                scraper::node::Node::Text(t) => eprintln!("{indent}[Text: {:?}]", t.text),
                scraper::node::Node::Element(e) => {
                    let attrs: Vec<_> = e.attrs().collect();
                    eprintln!("{indent}[Element: <{}> attrs={attrs:?}]", e.name());
                }
                scraper::node::Node::Comment(_) => eprintln!("{indent}[Comment]"),
                scraper::node::Node::ProcessingInstruction(_) => eprintln!("{indent}[PI]"),
            }
            for child in node.children() {
                walk(child, depth + 1);
            }
        }

        walk(fragment.tree.root(), 0);

        // Also print whether body selector finds anything
        let sel = scraper::Selector::parse("body").unwrap();
        let found = fragment.select(&sel).next();
        eprintln!("body selector found: {}", found.is_some());
        if let Some(b) = found {
            eprintln!("body children count: {}", b.children().count());
        }
    }
}
