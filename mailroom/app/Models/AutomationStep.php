<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StepType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationStep extends Model
{
    protected $fillable = ['position', 'type', 'config', 'campaign_id'];

    protected function casts(): array
    {
        return ['type' => StepType::class, 'config' => 'array'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function delayInSeconds(): int
    {
        $amount = max(0, (int) ($this->config['amount'] ?? 0));

        return $amount * match ($this->config['unit'] ?? 'days') {
            'minutes' => 60,
            'hours' => 3600,
            'weeks' => 604800,
            default => 86400,
        };
    }

    public function summary(): string
    {
        return match ($this->type) {
            StepType::Email => $this->campaign?->subject ?? 'Email',
            StepType::Wait => ($this->config['amount'] ?? 0).' '.($this->config['unit'] ?? 'days'),
            StepType::Tag, StepType::Untag => (string) ($this->config['tag'] ?? ''),
        };
    }
}
