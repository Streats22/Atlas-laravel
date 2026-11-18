<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;

/** Pre-send review: each item is ok, a warning, or an error that blocks sending. */
final class CampaignChecklist
{
    /** @return list<array{level:'ok'|'warning'|'error', title:string, detail:string}> */
    public function check(Campaign $campaign): array
    {
        $items = [];
        $add = function (string $level, string $title, string $detail = '') use (&$items): void {
            $items[] = compact('level', 'title', 'detail');
        };

        $recipients = $campaign->recipients()->count();
        $recipients > 0
            ? $add('ok', 'Recipients', number_format($recipients).' subscribed contacts will receive this campaign.')
            : $add('error', 'Recipients', 'Nobody matches the selected audience, segment and tag.');

        $add('ok', 'From', "{$campaign->from_name} <{$campaign->from_email}>");

        $subject = $campaign->subject;
        if (mb_strlen($subject) > 60) {
            $add('warning', 'Subject line', 'Subjects longer than 60 characters get cut off on most phones.');
        } elseif (preg_match('/!{2,}|\bFREE\b/', $subject) || ($subject === mb_strtoupper($subject) && preg_match('/\p{Lu}{4,}/u', $subject))) {
            $add('warning', 'Subject line', 'ALL CAPS, “FREE” and multiple exclamation marks can trigger spam filters.');
        } else {
            $add('ok', 'Subject line', $subject);
        }

        $campaign->preview_text
            ? $add('ok', 'Preview text', $campaign->preview_text)
            : $add('warning', 'Preview text', 'Add preview text to control the snippet shown after the subject.');

        $html = $campaign->html;
        preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/i', $html, $links);
        $bad = array_values(array_filter(array_unique($links[2]), fn (string $url) => ! preg_match('#^(https?://[^\s]+|mailto:|tel:|\{\{\s*unsubscribe_url\s*\}\})#i', $url) || preg_match('#^https?://(www\.)?example\.(com|org)#i', $url)));
        $bad
            ? $add('warning', 'Links', 'Check these links: '.implode(', ', array_slice($bad, 0, 5)))
            : $add('ok', 'Links', count(array_unique($links[2])).' links, all look valid.');

        preg_match_all('/<img\b[^>]*>/i', $html, $images);
        $noAlt = count(array_filter($images[0], fn (string $img) => ! preg_match('/\balt\s*=\s*(["\'])\s*[^"\']+\1/i', $img)));
        $noAlt > 0
            ? $add('warning', 'Images', "{$noAlt} image(s) have no alt text. People with images off or screen readers will miss them.")
            : $add('ok', 'Images', count($images[0]).' images, all with alt text.');

        preg_match_all('/\{\{\s*([a-z_]+)/', $html.$subject, $tags);
        $unknown = array_diff(array_unique($tags[1]), EmailRenderer::MERGE_TAGS);
        $unknown
            ? $add('error', 'Merge tags', 'Unknown merge tags: '.implode(', ', $unknown))
            : $add('ok', 'Merge tags', 'All merge tags are valid.');

        $kb = (int) ceil(strlen($html) / 1024);
        $kb > 100
            ? $add('warning', 'Email size', "{$kb} KB. Gmail clips messages over 102 KB and hides the rest.")
            : $add('ok', 'Email size', "{$kb} KB");

        return $items;
    }

    public function canSend(Campaign $campaign): bool
    {
        return ! collect($this->check($campaign))->contains('level', 'error');
    }
}
