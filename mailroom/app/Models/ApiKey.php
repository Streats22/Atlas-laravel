<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['name', 'prefix', 'token_hash', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Creates a key and returns the plain token once; only its SHA-256 hash is stored. */
    public static function issue(User $user, string $name): string
    {
        $token = 'mr_'.Str::random(40);
        $user->apiKeys()->create(['name' => $name, 'prefix' => substr($token, 0, 10), 'token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->with('user')->where('token_hash', hash('sha256', $token))->first();
    }
}
