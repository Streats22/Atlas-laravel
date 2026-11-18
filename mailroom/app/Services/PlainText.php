<?php

declare(strict_types=1);

namespace App\Services;

/** Builds the text/plain alternative part from campaign HTML (links kept as "text (url)"). */
final class PlainText
{
    public function fromHtml(string $html): string
    {
        $html = (string) preg_replace('#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $html);
        $html = (string) preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function (array $m): string {
                $text = trim(strip_tags($m[3]));
                $url = html_entity_decode($m[2]);

                return $text === '' || $text === $url ? $url : "{$text} ({$url})";
            },
            $html,
        );
        $html = (string) preg_replace('#<(br|/p|/h[1-6]|/tr|/li|/div)\b[^>]*>#i', "\n", $html);
        $html = (string) preg_replace('#<li\b[^>]*>#i', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = (string) preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = (string) preg_replace("/ *\n */", "\n", $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
