<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SegmentQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    protected $fillable = ['name', 'match', 'conditions'];

    protected function casts(): array
    {
        return ['conditions' => 'array'];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return Builder<Contact> */
    public function contacts(): Builder
    {
        return app(SegmentQuery::class)->apply(
            $this->audience->contacts()->getQuery(),
            $this->conditions,
            $this->match,
        );
    }
}
