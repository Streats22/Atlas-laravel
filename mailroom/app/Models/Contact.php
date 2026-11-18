<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactStatus;
use App\Services\AutomationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    /** Mirror the column defaults so new models behave like freshly loaded ones. */
    protected $attributes = ['status' => 'subscribed', 'source' => 'manual', 'soft_bounces' => 0];

    protected $fillable = [
        'email', 'first_name', 'last_name', 'fields', 'notes', 'status', 'source',
        'subscribed_at', 'confirmed_at', 'unsubscribed_at', 'consent_at', 'consent_text', 'signup_ip', 'soft_bounces',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'fields' => 'array',
            'consent_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Imports never trigger automations, so a bulk upload can't send a welcome series to everyone.
        static::saved(function (Contact $contact): void {
            $becameSubscribed = $contact->status === ContactStatus::Subscribed
                && ($contact->wasRecentlyCreated || $contact->wasChanged('status'));
            if ($becameSubscribed && $contact->source !== 'import') {
                app(AutomationEngine::class)->onSubscribed($contact);
            }
        });

        static::creating(function (Contact $contact): void {
            $contact->token ??= Str::random(40);
            $contact->email = Str::lower(trim($contact->email));
        });
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(CampaignSend::class);
    }

    public function scopeSubscribed(Builder $query): void
    {
        $query->where('status', ContactStatus::Subscribed);
    }

    public function field(string $key): ?string
    {
        $value = $this->fields[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * 1-5 star engagement rating: new contacts start at 2, opens and clicks raise it,
     * long silence lowers it.
     */
    public function rating(int $received, int $opened, int $clicked): int
    {
        if ($received === 0) {
            return 2;
        }
        $score = 1 + 2.5 * ($opened / $received) + 2.5 * min(1, $clicked / max(1, $received) * 3);

        return (int) max(1, min(5, round($score)));
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => ContactStatus::Unsubscribed, 'unsubscribed_at' => now()]);
    }

    public function confirm(): void
    {
        $this->update([
            'status' => ContactStatus::Subscribed,
            'confirmed_at' => now(),
            'subscribed_at' => $this->subscribed_at ?? now(),
        ]);
    }
}
