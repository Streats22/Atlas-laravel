<?php

declare(strict_types=1);

namespace App\Services;

use App\Builder\HtmlSanitizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Fetches RSS 2.0 / Atom feeds for RSS-to-email campaigns. Guards against SSRF: only public
 * http(s) hosts, no redirects, size-capped body, no external XML entities.
 */
final class FeedFetcher
{
    private const MAX_BYTES = 2_000_000;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /** @return array{title:string, items:list<array{title:string, link:string, summary:string, date:?CarbonImmutable}>} */
    public function fetch(string $url): array
    {
        $this->assertPublicUrl($url);
        $response = Http::timeout(10)->withOptions(['allow_redirects' => false])->accept('application/rss+xml, application/atom+xml, application/xml, text/xml')->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("Feed returned HTTP {$response->status()}.");
        }
        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Feed is too large.');
        }

        return $this->parse($body);
    }

    /** @return array{title:string, items:list<array{title:string, link:string, summary:string, date:?CarbonImmutable}>} */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            throw new RuntimeException('Not a valid RSS or Atom feed.');
        }

        $items = [];
        if (isset($doc->channel)) {
            foreach ($doc->channel->item as $item) {
                $items[] = $this->item((string) $item->title, (string) $item->link, (string) ($item->description ?? ''), (string) ($item->pubDate ?? ''));
            }
            $title = (string) $doc->channel->title;
        } else {
            foreach ($doc->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    if ((string) ($l['rel'] ?? 'alternate') === 'alternate') {
                        $link = (string) $l['href'];
                    }
                }
                $items[] = $this->item((string) $entry->title, $link, (string) ($entry->summary ?? $entry->content ?? ''), (string) ($entry->updated ?? $entry->published ?? ''));
            }
            $title = (string) $doc->title;
        }

        return ['title' => mb_substr(trim($title), 0, 190), 'items' => array_slice($items, 0, 50)];
    }

    /** @param  list<array{title:string, link:string, summary:string, date:?CarbonImmutable}>  $items */
    public function renderItems(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            $title = e($item['title']);
            $html .= '<div style="margin:0 0 24px">'
                .'<h3 style="margin:0 0 6px;font-size:19px">'.($item['link'] !== '' ? '<a href="'.e($item['link']).'">'.$title.'</a>' : $title).'</h3>'
                .($item['date'] ? '<div style="font-size:12px;color:#6b7280;margin-bottom:6px">'.e($item['date']->format('M j, Y')).'</div>' : '')
                .'<div>'.$item['summary'].'</div></div>';
        }

        return $html;
    }

    public function assertPublicUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user'])) {
            throw new RuntimeException('Only public http(s) feed URLs are allowed.');
        }
        $ips = filter_var($parts['host'], FILTER_VALIDATE_IP) ? [$parts['host']] : (gethostbynamel($parts['host']) ?: []);
        if ($ips === []) {
            throw new RuntimeException('Feed host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Feed URLs on private or internal networks are not allowed.');
            }
        }
    }

    /** @return array{title:string, link:string, summary:string, date:?CarbonImmutable} */
    private function item(string $title, string $link, string $summary, string $date): array
    {
        $link = trim($link);
        $clean = $this->sanitizer->clean(mb_substr($summary, 0, 5000));

        return [
            'title' => mb_substr(trim(html_entity_decode(strip_tags($title))), 0, 300),
            'link' => preg_match('#^https?://#i', $link) ? $link : '',
            'summary' => mb_strlen(strip_tags($clean)) > 600 ? e(mb_substr(trim(strip_tags($clean)), 0, 600)).'…' : $clean,
            'date' => $date !== '' ? rescue(fn () => CarbonImmutable::parse($date), null, false) : null,
        ];
    }
}
