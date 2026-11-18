<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Audience extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['name', 'from_name', 'from_email', 'double_opt_in', 'gdpr_enabled', 'gdpr_text'];

    protected function casts(): array
    {
        return ['double_opt_in' => 'boolean', 'gdpr_enabled' => 'boolean'];
    }

    /** The public id (used in hosted form URLs) is a ULID; the primary key stays an integer. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AudienceField::class)->orderBy('position')->orderBy('id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
