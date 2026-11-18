<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignSend extends Model
{
    protected $fillable = [
        'contact_id', 'variant_id', 'sent_at', 'failed_at', 'error', 'opened_at', 'open_count',
        'clicked_at', 'click_count', 'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CampaignSend $send): void {
            $send->token ??= Str::random(40);
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(CampaignVariant::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function recordOpen(): void
    {
        $this->open_count++;
        $this->opened_at ??= now();
        $this->save();
    }

    public function recordClick(): void
    {
        $this->click_count++;
        $this->clicked_at ??= now();
        // A click proves the mail was opened even if images were blocked.
        $this->opened_at ??= now();
        $this->open_count = max(1, $this->open_count);
        $this->save();
    }
}
