<?php

declare(strict_types=1);

namespace App\Services;

/** Small, dependency-free classification of the email client and device behind an open or click. */
final class UserAgent
{
    /** @return array{client:string, device:string} */
    public function classify(?string $ua): array
    {
        $ua = (string) $ua;
        $client = match (true) {
            str_contains($ua, 'GoogleImageProxy') => 'Gmail',
            str_contains($ua, 'YahooMailProxy') => 'Yahoo Mail',
            (bool) preg_match('/Microsoft Outlook|ms-office|MSOffice|Outlook-iOS|Outlook-Android/i', $ua) => 'Outlook',
            str_contains($ua, 'Thunderbird') => 'Thunderbird',
            (bool) preg_match('/iPhone|iPad/', $ua) && ! preg_match('/Safari|CriOS|FxiOS/', $ua) => 'Apple Mail (iOS)',
            str_contains($ua, 'Macintosh') && str_contains($ua, 'AppleWebKit') && ! preg_match('/Safari|Chrome/', $ua) => 'Apple Mail',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'Unknown',
            default => 'Other',
        };
        $device = match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua) => 'tablet',
            (bool) preg_match('/Mobile|iPhone|Android/i', $ua) => 'mobile',
            in_array($client, ['Gmail', 'Yahoo Mail', 'Unknown'], true) => 'unknown', // proxies hide the device
            default => 'desktop',
        };

        return ['client' => $client, 'device' => $device];
    }
}
