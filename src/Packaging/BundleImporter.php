<?php

declare(strict_types=1);

namespace Atlas\Packaging;

use Atlas\Atlas;
use Atlas\Blocks\BlockRegistry;
use Atlas\Blocks\FieldNormalizer;
use Atlas\Enums\PageStatus;
use Atlas\Models\CustomBlock;
use Atlas\Models\Page;
use Atlas\Support\PageMeta;
use Atlas\Support\SlugPolicy;
use Atlas\Support\Tree;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Loads a Bundle into this site. Existing pages/blocks are skipped unless $force is set. */
final class BundleImporter
{
    public function __construct(
        private readonly MediaUrls $urls,
        private readonly Atlas $atlas,
        private readonly FieldNormalizer $fields,
    ) {
    }

    public function import(Bundle $bundle, ?string $mediaDir = null, bool $force = false): ImportResult
    {
        $result = new ImportResult();

        DB::transaction(function () use ($bundle, $mediaDir, $force, $result): void {
            $this->importBlocks($bundle, $force, $result);
            $this->importPages($bundle, $force, $result);
            $this->importMedia($bundle, $mediaDir, $force, $result);
        });

        return $result;
    }

    private function importBlocks(Bundle $bundle, bool $force, ImportResult $result): void
    {
        foreach ($bundle->blocks as $raw) {
            $data = $this->urls->import($raw);
            $existing = CustomBlock::where('type', $data['type'] ?? '')->first();

            if (! $this->isValidType($data['type'] ?? null) || ($existing === null && $this->atlas->blocks()->has($data['type']))) {
                $result->blocksSkipped[] = (string) ($data['type'] ?? '?');

                continue;
            }
            if ($existing && ! $force) {
                $result->blocksSkipped[] = $data['type'];

                continue;
            }

            $payload = [
                'type' => $data['type'],
                'label' => (string) ($data['label'] ?? $data['type']),
                'category' => (string) ($data['category'] ?? 'Custom'),
                'icon' => $data['icon'] ?? null,
                'container' => (bool) ($data['container'] ?? false),
                'fields' => $this->fields->normalizeAll((array) ($data['fields'] ?? [])),
                'html' => $data['html'] ?? null,
                'css' => $data['css'] ?? null,
                'js' => $data['js'] ?? null,
            ];

            $existing ? $existing->update($payload) : CustomBlock::create($payload);
            $existing ? $result->blocksUpdated++ : $result->blocksCreated++;
        }
    }

    private function importPages(Bundle $bundle, bool $force, ImportResult $result): void
    {
        foreach ($bundle->pages as $raw) {
            $data = $this->urls->import($raw);
            $slug = (string) ($data['slug'] ?? '');
            $existing = SlugPolicy::usable($slug) ? Page::where('slug', $slug)->first() : null;

            // Same rules as the editor: valid, non-reserved slug; never clobber without --force.
            if (! SlugPolicy::usable($slug) || ($existing && ! $force)) {
                $result->pagesSkipped[] = $slug !== '' ? $slug : '?';

                continue;
            }

            $payload = [
                'title' => (string) ($data['title'] ?? $slug),
                'slug' => $slug,
                'status' => PageStatus::tryFrom((string) ($data['status'] ?? '')) ?? PageStatus::Draft,
                'content' => Tree::sanitize($data['content'] ?? []),
                'css' => $data['css'] ?? null,
                'js' => $data['js'] ?? null,
                'head' => $data['head'] ?? null,
                'meta' => PageMeta::sanitize((array) ($data['meta'] ?? []))->toArray(),
                'published_at' => $this->date($data['published_at'] ?? null),
            ];

            $existing ? $existing->update($payload) : Page::create($payload);
            $existing ? $result->pagesUpdated++ : $result->pagesCreated++;
        }
    }

    private function importMedia(Bundle $bundle, ?string $mediaDir, bool $force, ImportResult $result): void
    {
        if ($mediaDir === null) {
            return;
        }

        $disk = Storage::disk($this->urls->disk());

        foreach ($bundle->media as $relative) {
            $source = $mediaDir . '/' . $relative;
            $target = $this->urls->path($relative);

            // Only plain files inside the media directory, never outside it.
            if (! MediaUrls::isSafeName($relative) || ! is_file($source) || ($disk->exists($target) && ! $force)) {
                continue;
            }

            $disk->put($target, (string) file_get_contents($source));
            $result->mediaCopied++;
        }
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isValidType(mixed $type): bool
    {
        return is_string($type) && preg_match(BlockRegistry::TYPE_PATTERN, $type) === 1;
    }
}
