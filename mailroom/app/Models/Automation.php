<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutomationTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    protected $fillable = ['audience_id', 'name', 'trigger', 'trigger_value', 'status'];

    protected function casts(): array
    {
        return ['trigger' => AutomationTrigger::class];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class)->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function triggerDescription(): string
    {
        return $this->trigger === AutomationTrigger::TagAdded
            ? "Tag “{$this->trigger_value}” is added"
            : $this->trigger->label();
    }
}
