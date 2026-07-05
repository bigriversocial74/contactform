<?php
declare(strict_types=1);

/**
 * Strict public blog HTML sanitizer.
 *
 * Blog bodies are author-controlled HTML, but they render on public pages. This
 * helper intentionally keeps the article formatting set small and strips all
 * unsupported elements and attributes. Inline images are intentionally not
 * allowed here; featured images are handled through the hardened media path.
 */

function mg_blog_sanitize_url(?string $url): ?string
{
    $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
        return null;
    }

    $lower = strtolower($url);
    if (preg_match('/^(javascript|vbscript|data):/i', $lower) === 1) {
        return null;
    }

    if ($url[0] === '#') {
        return preg_match('/^#[A-Za-z0-9_-]{1,80}$/', $url) === 1 ? $url : null;
    }

    if ($url[0] === '/' && !str_starts_with($url, '//') && !str_contains($url, '\\')) {
        return $url;
    }

    if (preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
        return $url;
    }

    if (preg_match('/^mailto:[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/i', $url) === 1) {
        return $url;
    }

    return null;
}

function mg_blog_sanitize_fallback(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a><figure><figcaption><code><pre><hr>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/<(script|style|iframe|object|embed|svg|math|form|input|button|textarea|select|option)[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*(javascript|vbscript|data):[^"\']*("|\')/i', '', $html) ?? $html;
    return trim($html);
}

function mg_blog_sanitize_dom_node(DOMDocument $doc, DOMNode $node): void
{
    $removeWithChildren = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'option'];
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'figure', 'figcaption', 'code', 'pre', 'hr'];

    foreach (iterator_to_array($node->childNodes) as $child) {
        mg_blog_sanitize_dom_node($doc, $child);
    }

    if ($node instanceof DOMComment || $node->nodeType === XML_PI_NODE) {
        $node->parentNode?->removeChild($node);
        return;
    }

    if (!($node instanceof DOMElement)) {
        return;
    }

    $tag = strtolower($node->tagName);
    if (in_array($tag, $removeWithChildren, true)) {
        $node->parentNode?->removeChild($node);
        return;
    }

    if (!in_array($tag, $allowed, true)) {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }
        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }
        $parent->removeChild($node);
        return;
    }

    $href = $tag === 'a' ? $node->getAttribute('href') : null;
    foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
        $node->removeAttribute($attribute->name);
    }

    if ($tag === 'a') {
        $safeHref = mg_blog_sanitize_url($href);
        if ($safeHref !== null) {
            $node->setAttribute('href', $safeHref);
            if (preg_match('#^https?://#i', $safeHref) === 1) {
                $node->setAttribute('target', '_blank');
                $node->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }
}

function mg_blog_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return mg_blog_sanitize_fallback($html);
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div id="mg-blog-sanitize-root">' . $html . '</div>';
        $flags = LIBXML_NONET;
        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $flags |= LIBXML_HTML_NOIMPLIED;
        }
        if (defined('LIBXML_HTML_NODEFDTD')) {
            $flags |= LIBXML_HTML_NODEFDTD;
        }
        $doc->loadHTML('<?xml encoding="UTF-8">' . $wrapped, $flags);
        $root = $doc->getElementById('mg-blog-sanitize-root');
        if (!$root) {
            return mg_blog_sanitize_fallback($html);
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            mg_blog_sanitize_dom_node($doc, $child);
        }

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $doc->saveHTML($child);
        }
        return trim($output);
    } catch (Throwable) {
        return mg_blog_sanitize_fallback($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function mg_blog_render_body(string $html): string
{
    return mg_blog_sanitize_html($html);
}
