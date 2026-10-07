<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Illuminate\Support\Facades\Storage;

/** Collects pages, the builder blocks they use and the media they reference into a Bundle. */
final class BundleExporter
{
    public function __construct(private readonly MediaUrls $urls)
    {
    }

    /**
     * @param  list<string>|null  $slugs  Only these pages (null = all)
     * @param  bool  $allBlocks  Include every builder block, not only the ones the pages use
     */
    public function export(?array $slugs = null, bool $allBlocks = false, bool $withMedia = true): Bundle
    {
        $pages = Page::query()
            ->when($slugs, fn ($q) => $q->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get()
            ->map(fn (Page $page) => $this->urls->export([
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->status->value,
                'content' => $page->content ?? [],
                'css' => $page->css,
                'js' => $page->js,
                'head' => $page->head,
                'meta' => $page->meta ?? [],
                'published_at' => $page->published_at?->toIso8601String(),
            ]))
            ->all();

        $blocks = CustomBlock::query()->orderBy('type')->get()
            ->filter(fn (CustomBlock $b) => $allBlocks || $slugs === null || $this->isUsed($b->type, $pages))
            ->map(fn (CustomBlock $b) => $this->urls->export(collect($b->toBuilder())->except('id')->all()))
            ->values()
            ->all();

        $media = $withMedia ? $this->existingMedia($this->urls->referenced(['pages' => $pages, 'blocks' => $blocks])) : [];

        return new Bundle($pages, $blocks, $media, now()->toIso8601String());
    }

    /** @param  list<array<string, mixed>>  $pages */
    private function isUsed(string $type, array $pages): bool
    {
        return $this->containsType($type, array_column($pages, 'content'));
    }

    private function containsType(string $type, array $trees): bool
    {
        foreach ($trees as $nodes) {
            foreach ((array) $nodes as $node) {
                if (($node['type'] ?? null) === $type || $this->containsType($type, [$node['children'] ?? []])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  list<string>  $paths */
    private function existingMedia(array $paths): array
    {
        $disk = Storage::disk($this->urls->disk());

        return array_values(array_filter($paths, fn (string $name) => $disk->exists($this->urls->path($name))));
    }
}
