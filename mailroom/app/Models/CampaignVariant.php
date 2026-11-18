<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignVariant extends Model
{
    protected $fillable = ['label', 'subject', 'from_name', 'html', 'design', 'send_delay_hours', 'is_winner'];

    protected function casts(): array
    {
        return ['design' => 'array', 'is_winner' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class, 'variant_id');
    }

    /**
     * The campaign as this variant sends it: an unsaved copy (same id, so links and tracking
     * stay attached to the campaign) with the variant's overrides applied.
     */
    public function apply(Campaign $campaign): Campaign
    {
        $copy = clone $campaign;
        $copy->subject = $this->subject ?: $campaign->subject;
        $copy->from_name = $this->from_name ?: $campaign->from_name;
        $copy->html = $this->html ?: $campaign->html;

        return $copy;
    }

    /** @return array{sent:int, opens:int, clicks:int, open_rate:float, click_rate:float} */
    public function stats(): array
    {
        $sent = $this->sends()->whereNotNull('sent_at')->count();
        $opens = $this->sends()->whereNotNull('opened_at')->count();
        $clicks = $this->sends()->whereNotNull('clicked_at')->count();

        return [
            'sent' => $sent, 'opens' => $opens, 'clicks' => $clicks,
            'open_rate' => $sent ? round($opens / $sent * 100, 1) : 0.0,
            'click_rate' => $sent ? round($clicks / $sent * 100, 1) : 0.0,
        ];
    }
}
