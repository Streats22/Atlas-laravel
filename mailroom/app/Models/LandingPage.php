<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPage extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['published' => false, 'visits' => 0, 'signups' => 0];

    protected $fillable = ['audience_id', 'title', 'slug', 'meta_description', 'design', 'html', 'tag', 'published'];

    protected function casts(): array
    {
        return ['design' => 'array', 'published' => 'boolean'];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function conversionRate(): float
    {
        return $this->visits > 0 ? round($this->signups / $this->visits * 100, 1) : 0.0;
    }
}
