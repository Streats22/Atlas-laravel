<?php

declare(strict_types=1);

namespace Atlas\Models;

use Atlas\Enums\PageStatus;
use Atlas\Facades\Atlas;
use Atlas\Support\Locales;
use Atlas\Support\PageMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 * @property array $content   Block tree
 * @property ?string $css
 * @property ?string $js
 * @property ?string $head
 * @property ?array $meta
 * @property ?\Illuminate\Support\Carbon $published_at
 */
class Page extends Model
{
    protected $table = 'atlas_pages';

    protected $fillable = ['title', 'slug', 'status', 'content', 'css', 'js', 'head', 'meta', 'published_at'];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'content' => 'array',
            'meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function metaBag(): PageMeta
    {
        return PageMeta::from($this->meta);
    }

    public function url(?string $locale = null): string
    {
        return Locales::url($this, $locale ?? Locales::default());
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
