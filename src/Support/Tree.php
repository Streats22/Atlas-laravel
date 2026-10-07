<?php

declare(strict_types=1);

namespace Atlas\Support;

use Illuminate\Support\Str;

/**
 * Normalises a block tree coming from the browser:
 * [{ id, type, props: {}, children: [] }, ...]
 */
class Tree
{
    public const MAX_DEPTH = 24;

    public const MAX_NODES = 3000;

    public static function sanitize(mixed $nodes): array
    {
        $seen = [];
        $count = 0;

        return self::walk(is_array($nodes) ? $nodes : [], 0, $seen, $count);
    }

    private static function walk(array $nodes, int $depth, array &$seen, int &$count): array
    {
        $out = [];

        foreach (array_values($nodes) as $node) {
            if (! is_array($node) || $count >= self::MAX_NODES) {
                continue;
            }

            $type = $node['type'] ?? null;
            if (! is_string($type) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $type)) {
                continue;
            }

            $count++;

            $id = $node['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $id) || isset($seen[$id])) {
                do {
                    $id = Str::lower(Str::random(8));
                } while (isset($seen[$id]));
            }
            $seen[$id] = true;

            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $children = is_array($node['children'] ?? null) && $depth < self::MAX_DEPTH
                ? self::walk($node['children'], $depth + 1, $seen, $count)
                : [];

            $out[] = [
                'id' => $id,
                'type' => $type,
                'props' => $props,
                'children' => $children,
            ];
        }

        return $out;
    }

    /** Prepare a stored tree for JSON output so empty props encode as {} not []. */
    public static function forClient(array $nodes): array
    {
        return array_map(fn ($n) => [
            'id' => $n['id'] ?? null,
            'type' => $n['type'] ?? null,
            'props' => (object) ($n['props'] ?? []),
            'children' => self::forClient($n['children'] ?? []),
        ], $nodes);
    }
}
