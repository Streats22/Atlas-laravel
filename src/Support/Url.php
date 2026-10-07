<?php

namespace Atlas\Support;

class Url
{
    /** Neutralise javascript:, data: and similar schemes in link/src values. */
    public static function safe(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $probe = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url));

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)
            && ! in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        return $url;
    }
}
