<?php

declare(strict_types=1);

namespace Atlas\Http\Controllers;

use Atlas\Models\Page;
use Atlas\Support\Locales;
use Illuminate\Http\Response;

/** /sitemap.xml — every published page, with hreflang alternates when several locales are configured. */
class SitemapController
{
    public function __invoke(): Response
    {
        $multiple = Locales::multiple();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach (Page::published()->orderBy('slug')->get() as $page) {
            $xml .= '  <url><loc>' . e($page->url()) . '</loc><lastmod>' . $page->updated_at?->toAtomString() . '</lastmod>';

            if ($multiple) {
                foreach (array_keys(Locales::available()) as $code) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="' . e($code) . '" href="' . e(Locales::url($page, $code)) . '"/>';
                }
            }

            $xml .= "</url>\n";
        }

        return response($xml . '</urlset>')->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
