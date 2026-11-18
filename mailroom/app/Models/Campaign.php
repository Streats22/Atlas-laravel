<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    protected $fillable = [
        'kind', 'audience_id', 'segment_id', 'tag_id', 'name', 'subject', 'preview_text',
        'from_name', 'from_email', 'html', 'design', 'status', 'scheduled_at', 'sent_at', 'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'design' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function automationStep(): HasOne
    {
        return $this->hasOne(AutomationStep::class);
    }

    public function isAutomation(): bool
    {
        return $this->kind === 'automation';
    }

    public function links(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    /**
     * Subscribed contacts this campaign targets: whole audience, a segment or a tag.
     *
     * @return Builder<Contact>
     */
    public function recipients(): Builder
    {
        $query = $this->segment
            ? $this->segment->contacts()
            : $this->audience->contacts()->getQuery();

        if ($this->tag_id !== null) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereKey($this->tag_id));
        }

        return $query->subscribed();
    }

    /** @return array{sent:int, opens:int, clicks:int, unsubscribes:int, failed:int, open_rate:float, click_rate:float} */
    public function stats(): array
    {
        $sends = $this->sends();
        $sent = (clone $sends)->whereNotNull('sent_at')->count();
        $opens = (clone $sends)->whereNotNull('opened_at')->count();
        $clicks = (clone $sends)->whereNotNull('clicked_at')->count();

        return [
            'sent' => $sent,
            'opens' => $opens,
            'clicks' => $clicks,
            'unsubscribes' => (clone $sends)->whereNotNull('unsubscribed_at')->count(),
            'failed' => (clone $sends)->whereNotNull('failed_at')->count(),
            'open_rate' => $sent > 0 ? round($opens / $sent * 100, 1) : 0.0,
            'click_rate' => $sent > 0 ? round($clicks / $sent * 100, 1) : 0.0,
        ];
    }
}
