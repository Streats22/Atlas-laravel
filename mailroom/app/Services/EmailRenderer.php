<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignSend;
use App\Models\Contact;

/**
 * Produces the final HTML for one recipient: merge tags, tracked links, open pixel and footer.
 */
final class EmailRenderer
{
    /** Merge tags available in subject and body, e.g. {{ first_name }}. */
    public const MERGE_TAGS = ['first_name', 'last_name', 'full_name', 'email', 'unsubscribe_url', 'audience_name'];

    public function render(Campaign $campaign, Contact $contact, ?CampaignSend $send = null): string
    {
        $unsubscribeUrl = route('public.unsubscribe', ['token' => $contact->token, 'send' => $send?->token]);
        $html = $this->merge($campaign->html, $contact, $unsubscribeUrl, html: true);

        if ($send !== null) {
            $html = $this->trackLinks($campaign, $send, $html);
        }

        $html = $this->withPreheader($html, (string) $campaign->preview_text);
        $footer = view('emails.footer', [
            'campaign' => $campaign,
            'unsubscribeUrl' => $unsubscribeUrl,
            'pixel' => $send ? route('track.open', $send->token) : null,
        ])->render();

        return str_contains($html, '</body>')
            ? str_replace('</body>', $footer.'</body>', $html)
            : $html.$footer;
    }

    public function subject(Campaign $campaign, Contact $contact): string
    {
        return $this->merge($campaign->subject, $contact, '', html: false);
    }

    private function merge(string $text, Contact $contact, string $unsubscribeUrl, bool $html): string
    {
        $values = [
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'full_name' => $contact->fullName(),
            'email' => $contact->email,
            'unsubscribe_url' => $unsubscribeUrl,
            'audience_name' => (string) $contact->audience?->name,
        ];

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)(?:\s*\|\s*([^}]*?))?\s*\}\}/',
            function (array $m) use ($values, $html): string {
                if (! array_key_exists($m[1], $values)) {
                    return $m[0];
                }
                $value = $values[$m[1]] !== '' ? $values[$m[1]] : trim($m[2] ?? '');

                return $html ? e($value) : $value;
            },
            $text,
        );
    }

    private function trackLinks(Campaign $campaign, CampaignSend $send, string $html): string
    {
        return (string) preg_replace_callback(
            '/(<a\b[^>]*\bhref\s*=\s*)(["\'])(https?:\/\/[^"\']+)\2/i',
            function (array $m) use ($campaign, $send): string {
                $url = html_entity_decode($m[3], ENT_QUOTES);
                if (str_starts_with($url, url('/u/'))) {
                    return $m[0];
                }
                $hash = substr(hash('sha256', $url), 0, 16);
                $campaign->links()->firstOrCreate(['hash' => $hash], ['url' => $url]);

                return $m[1].$m[2].e(route('track.click', ['token' => $send->token, 'hash' => $hash])).$m[2];
            },
            $html,
        );
    }

    private function withPreheader(string $html, string $preview): string
    {
        if ($preview === '') {
            return $html;
        }
        $hidden = '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($preview).'</div>';

        return preg_match('/<body[^>]*>/i', $html)
            ? (string) preg_replace('/(<body[^>]*>)/i', '$1'.$hidden, $html, 1)
            : $hidden.$html;
    }
}
