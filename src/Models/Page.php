<?php

namespace Atlas\Models;

use Atlas\Facades\Atlas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $status
 * @property array $content   Block tree
 * @property ?string $css
 * @property ?string $js
 * @property ?string $head
 * @property ?array $meta
 */
class Page extends Model
{
    protected $table = 'atlas_pages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function url(?string $locale = null): string
    {
        return \Atlas\Support\Locales::url($this, $locale ?? \Atlas\Support\Locales::default());
    }

    /** The block tree rendered to HTML (no layout). */
    public function renderBody(): HtmlString
    {
        return Atlas::renderer()->render($this->content ?? []);
    }

    /** A complete HTML document using the configured layout. */
    public function render(bool $editing = false): string
    {
        return Atlas::renderer()->document($this, $editing);
    }
}
