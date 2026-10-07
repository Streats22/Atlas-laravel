<?php

declare(strict_types=1);

namespace Atlas\Rendering;

use Atlas\Models\Page;
use Atlas\Support\PageMeta;

/** A uniform, read-only view over a stored Page or an unsaved editor payload. */
final class PageData
{
    public function __construct(
        public readonly string $title,
        public readonly array $content,
        public readonly ?string $css,
        public readonly ?string $js,
        public readonly ?string $head,
        public readonly PageMeta $meta,
        public readonly ?Page $model = null,
    ) {
    }

    public static function from(Page|array $page): self
    {
        if ($page instanceof Page) {
            return new self(
                (string) $page->title,
                (array) ($page->content ?? []),
                $page->css,
                $page->js,
                $page->head,
                PageMeta::from($page->meta),
                $page,
            );
        }

        return new self(
            (string) ($page['title'] ?? ''),
            (array) ($page['content'] ?? []),
            $page['css'] ?? null,
            $page['js'] ?? null,
            $page['head'] ?? null,
            PageMeta::from($page['meta'] ?? []),
        );
    }
}
