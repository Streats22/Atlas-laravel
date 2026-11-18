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
    /** Mirror the column defaults so new models behave like freshly loaded ones. */
    protected $attributes = ['kind' => 'regular', 'status' => 'draft', 'recipients_count' => 0, 'optimize_send_time' => false];

    protected $fillable = [
        'kind', 'audience_id', 'segment_id', 'tag_id', 'resend_of_id', 'parent_id', 'rss', 'name', 'subject', 'preview_text',
        'from_name', 'from_email', 'html', 'design', 'ab_test', 'status', 'scheduled_at', 'optimize_send_time', 'sent_at', 'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'design' => 'array',
            'ab_test' => 'array',
            'rss' => 'array',
            'scheduled_at' => 'datetime',
            'optimize_send_time' => 'boolean',
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

    public function resendOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resend_of_id');
    }

    /** Signed, unguessable id for the public web version and archive. */
    public function publicHash(): string
    {
        return substr(hash_hmac('sha256', 'campaign:'.$this->id, (string) config('app.key')), 0, 20);
    }

    public function webUrl(): string
    {
        return route('public.campaign', ['campaign' => $this->id, 'hash' => $this->publicHash()]);
    }

    public function isAutomation(): bool
    {
        return $this->kind === 'automation';
    }

    public function variants(): HasMany
    {
        return $this->hasMany(CampaignVariant::class)->orderBy('label');
    }

    public function hasAbTest(): bool
    {
        return ! empty($this->ab_test['enabled']);
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
        if ($this->resend_of_id !== null) {
            // Resend to non-openers: only people who received the original and didn't open it.
            $query->whereHas('sends', fn (Builder $q) => $q->where('campaign_id', $this->resend_of_id)->whereNotNull('sent_at')->whereNull('opened_at'));
        }

        return $query->subscribed();
    }

    /** @return array{sent:int, opens:int, clicks:int, unsubscribes:int, failed:int, bounces:int, complaints:int, open_rate:float, click_rate:float} */
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
            'bounces' => (clone $sends)->whereNotNull('bounced_at')->count(),
            'complaints' => (clone $sends)->whereNotNull('complained_at')->count(),
            'open_rate' => $sent > 0 ? round($opens / $sent * 100, 1) : 0.0,
            'click_rate' => $sent > 0 ? round($clicks / $sent * 100, 1) : 0.0,
        ];
    }
}
