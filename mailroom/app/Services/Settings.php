<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Application settings stored in the database. Secret keys are encrypted with APP_KEY at rest.
 */
final class Settings
{
    private const SECRETS = ['mail_password'];

    private const CACHE_KEY = 'mailroom.settings';

    /** @return array<string, string|null> */
    public function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->map(
                fn (?string $value, string $key) => in_array($key, self::SECRETS, true) && $value !== null ? $this->decrypt($value) : $value,
            )->all());
        } catch (Throwable) {
            return []; // Table not migrated yet.
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    /** @param  array<string, string|null>  $values */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            $stored = in_array($key, self::SECRETS, true) && $value !== null && $value !== '' ? Crypt::encryptString($value) : $value;
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    private function decrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null; // APP_KEY rotated: the user re-enters the password.
        }
    }
}
