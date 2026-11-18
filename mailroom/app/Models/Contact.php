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

    protected $fillable = [
        'email', 'first_name', 'last_name', 'fields', 'notes', 'status', 'source',
        'subscribed_at', 'confirmed_at', 'unsubscribed_at', 'consent_at', 'consent_text', 'signup_ip',
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
