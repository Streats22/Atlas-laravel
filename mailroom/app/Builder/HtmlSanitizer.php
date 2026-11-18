<?php

declare(strict_types=1);

namespace App\Builder;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist sanitizer for rich-text blocks: keeps basic formatting and safe links, drops everything else.
 */
final class HtmlSanitizer
{
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ul', 'ol', 'li', 'span', 'h2', 'h3'];

    public function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');
        if ($root === null) {
            return e(strip_tags($html));
        }
        $this->walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math'], true)) {
                    $node->removeChild($child);

                    continue;
                }
                $this->walk($child);
                if (! in_array($tag, self::ALLOWED, true)) {
                    $this->unwrap($child);

                    continue;
                }
                $this->cleanAttributes($child, $tag);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function cleanAttributes(DOMElement $el, string $tag): void
    {
        $href = $tag === 'a' ? Values::url($el->getAttribute('href')) : '';
        foreach (iterator_to_array($el->attributes) as $attr) {
            $el->removeAttribute($attr->nodeName);
        }
        if ($tag === 'a') {
            if ($href === '') {
                $this->unwrap($el);

                return;
            }
            $el->setAttribute('href', $href);
        }
    }

    private function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }
}
