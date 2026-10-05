<?php

declare(strict_types=1);

namespace System\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Pure PHP HTML Sanitizer protecting against Cross-Site Scripting (XSS)
 * while preserving safe rich-text formatting, media tags, and Unicode (Bangla, etc.).
 */
class HtmlSanitizer
{
    /**
     * Tags allowed by default in rich-text content.
     */
    public const DEFAULT_ALLOWED_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'b', 'strong', 'i', 'em', 'u', 's', 'del', 'sub', 'sup',
        'a', 'img', 'blockquote', 'code', 'pre', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'div', 'span', 'figure', 'figcaption',
    ];

    /**
     * Tags whose entire tree (including text and children) must be discarded.
     */
    public const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object',
        'embed', 'applet', 'meta', 'link', 'base', 'form', 'input',
        'button', 'select', 'textarea', 'svg', 'math',
    ];

    /**
     * Global attributes permitted on any allowed element.
     */
    public const GLOBAL_ALLOWED_ATTRIBUTES = [
        'class', 'id', 'title', 'dir', 'lang', 'style',
    ];

    /**
     * Specific attributes allowed on individual tags.
     */
    public const TAG_ALLOWED_ATTRIBUTES = [
        'a'     => ['href', 'target', 'rel', 'title'],
        'img'   => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'table' => ['border', 'cellpadding', 'cellspacing', 'summary'],
        'th'    => ['colspan', 'rowspan', 'align', 'valign', 'scope'],
        'td'    => ['colspan', 'rowspan', 'align', 'valign'],
        'ol'    => ['start', 'type', 'reversed'],
        'li'    => ['value'],
    ];

    /**
     * Clean untrusted HTML string and return XSS-safe HTML markup.
     */
    public static function clean(string $html, ?array $allowedTags = null): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Fast path: if there are no HTML tags, return escaped string or plain text
        if (!str_contains($html, '<')) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $allowedTags = $allowedTags !== null ? array_map('strtolower', $allowedTags) : self::DEFAULT_ALLOWED_TAGS;
        $allowedMap = array_fill_keys($allowedTags, true);
        $stripMap = array_fill_keys(self::DANGEROUS_TAGS, true);

        // Pre-strip script/style tags and comments to prevent parser confusion
        $preCleaned = preg_replace('/<!--.*?-->/s', '', $html);
        $preCleaned = preg_replace('/<\s*(script|style|svg|math|iframe|object|embed)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', (string) $preCleaned);

        $doc = new DOMDocument();
        $previousEntityLoader = libxml_use_internal_errors(true);

        // Wrap with UTF-8 HTML document structure so DOMDocument does not convert multibyte characters to entities
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $preCleaned . '</body></html>';
        @$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousEntityLoader);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }

        self::cleanChildren($body, $allowedMap, $stripMap);

        $output = '';
        foreach ($body->childNodes as $node) {
            $output .= $doc->saveHTML($node);
        }

        return trim($output);
    }

    /**
     * Recursively clean a node's children.
     */
    protected static function cleanChildren(DOMNode $parent, array $allowedMap, array $stripMap): void
    {
        $current = $parent->firstChild;

        while ($current !== null) {
            $next = $current->nextSibling;

            if ($current instanceof DOMElement) {
                $tag = strtolower($current->tagName);

                if (isset($stripMap[$tag])) {
                    $parent->removeChild($current);
                } elseif (!isset($allowedMap[$tag])) {
                    // Tag is not allowed: unwrap its safe children or discard
                    self::cleanChildren($current, $allowedMap, $stripMap);
                    while ($current->firstChild) {
                        $parent->insertBefore($current->firstChild, $current);
                    }
                    $parent->removeChild($current);
                } else {
                    // Allowed tag: sanitize attributes and clean children
                    self::cleanAttributes($current);
                    self::cleanChildren($current, $allowedMap, $stripMap);
                }
            } elseif ($current->nodeType !== XML_TEXT_NODE && $current->nodeType !== XML_CDATA_SECTION_NODE) {
                // Remove comments, processing instructions, etc.
                $parent->removeChild($current);
            }

            $current = $next;
        }
    }

    /**
     * Sanitize element attributes: strip dangerous event handlers and unauthorized attributes.
     */
    protected static function cleanAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $allowedAttrs = array_merge(
            self::GLOBAL_ALLOWED_ATTRIBUTES,
            self::TAG_ALLOWED_ATTRIBUTES[$tag] ?? []
        );
        $allowedAttrMap = array_fill_keys($allowedAttrs, true);

        $toRemove = [];

        foreach ($element->attributes as $attr) {
            $name = strtolower($attr->name);
            $val = trim($attr->value);

            // 1. Strip all inline event handlers (onload, onerror, onclick, etc.)
            if (str_starts_with($name, 'on')) {
                $toRemove[] = $attr->name;
                continue;
            }

            // 2. Strip unapproved attributes
            if (!isset($allowedAttrMap[$name])) {
                $toRemove[] = $attr->name;
                continue;
            }

            // 3. Validate URLs on href and src
            if ($name === 'href' || $name === 'src') {
                $isImg = ($tag === 'img' && $name === 'src');
                if (!self::isSafeUrl($val, $isImg)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
            }

            // 4. Sanitize inline style attributes
            if ($name === 'style') {
                if (!self::isSafeStyle($val)) {
                    $toRemove[] = $attr->name;
                    continue;
                }
            }
        }

        foreach ($toRemove as $attrName) {
            $element->removeAttribute($attrName);
        }

        // 5. Hardening: target="_blank" must always enforce rel="noopener noreferrer"
        if ($tag === 'a' && strtolower((string) $element->getAttribute('target')) === '_blank') {
            $rel = strtolower((string) $element->getAttribute('rel'));
            $relParts = array_filter(explode(' ', $rel));
            if (!in_array('noopener', $relParts, true)) {
                $relParts[] = 'noopener';
            }
            if (!in_array('noreferrer', $relParts, true)) {
                $relParts[] = 'noreferrer';
            }
            $element->setAttribute('rel', implode(' ', $relParts));
        }
    }

    /**
     * Determine if a URL is safe for href or src attributes.
     */
    public static function isSafeUrl(string $url, bool $allowDataImage = false): bool
    {
        $clean = trim($url);
        if ($clean === '') {
            return false;
        }

        // Relative URLs (starting with /, #, ?, or relative paths without scheme before /)
        if (str_starts_with($clean, '/') || str_starts_with($clean, '#') || str_starts_with($clean, '?')) {
            return true;
        }

        // Strip ASCII control characters and whitespace
        $clean = preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $clean);

        // Decode HTML entities (e.g. java&#09;script:)
        $decoded = html_entity_decode((string) $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = strtolower(preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $decoded));

        // Block dangerous schemes
        if (
            str_starts_with($normalized, 'javascript:') ||
            str_starts_with($normalized, 'vbscript:') ||
            str_starts_with($normalized, 'file:')
        ) {
            return false;
        }

        // Allow data:image for images only
        if (str_starts_with($normalized, 'data:')) {
            return $allowDataImage && (bool) preg_match('#^data:image/(?:png|jpeg|jpg|gif|webp);base64,[A-Za-z0-9+/=]+$#i', $clean);
        }

        // Standard safe web schemes
        if (
            str_starts_with($normalized, 'http://') ||
            str_starts_with($normalized, 'https://') ||
            str_starts_with($normalized, 'mailto:') ||
            str_starts_with($normalized, 'tel:')
        ) {
            return true;
        }

        // If it doesn't contain a colon (:), treat as relative link (e.g. "about", "posts/1")
        return !str_contains($clean, ':');
    }

    /**
     * Determine if CSS declarations in a style attribute are safe.
     */
    public static function isSafeStyle(string $style): bool
    {
        $normalized = strtolower($style);

        // Decode entities
        $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Dangerous CSS constructs
        $dangerousPatterns = [
            'javascript:',
            'expression(',
            'behavior:',
            '-moz-binding',
            '@import',
            'url(',
            'binding:',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (str_contains($decoded, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
