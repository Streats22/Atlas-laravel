<?php

namespace Atlas\Support;

/**
 * A tiny logic-light template language for blocks built in the editor.
 *
 *   {{ field }}            HTML-escaped value
 *   {{{ field }}}          raw value (trusted code)
 *   {{ url:field }}        escaped, with javascript:/data: links neutralised
 *   {{#each items}}…{{/each}}   loop (inside: item fields, {{ @index }}, {{ @number }})
 *   {{#if field}}…{{else}}…{{/if}}
 *
 * It never executes PHP, so it is safe to expose to non-developers.
 */
class Template
{
    public static function render(string $tpl, array $context): string
    {
        $tokens = self::tokenize($tpl);
        $i = 0;
        $ast = self::parse($tokens, $i);

        return self::run($ast, [$context]);
    }

    private static function tokenize(string $tpl): array
    {
        $re = '/\{\{\{\s*(.+?)\s*\}\}\}|\{\{\s*#(each|if)\s+([\w@.-]+)\s*\}\}|\{\{\s*\/(each|if)\s*\}\}|\{\{\s*else\s*\}\}|\{\{\s*([^{}]+?)\s*\}\}/s';
        $tokens = [];
        $pos = 0;

        preg_match_all($re, $tpl, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($m as $match) {
            $start = $match[0][1];
            if ($start > $pos) {
                $tokens[] = ['text', substr($tpl, $pos, $start - $pos)];
            }
            $full = $match[0][0];
            if (($match[1][1] ?? -1) >= 0 && $match[1][0] !== '' && str_starts_with($full, '{{{')) {
                $tokens[] = ['raw', $match[1][0]];
            } elseif (! empty($match[2][0])) {
                $tokens[] = ['open', $match[2][0], $match[3][0]];
            } elseif (! empty($match[4][0] ?? '')) {
                $tokens[] = ['close', $match[4][0]];
            } elseif (preg_match('/^\{\{\s*else\s*\}\}$/', $full)) {
                $tokens[] = ['else'];
            } else {
                $tokens[] = ['esc', $match[5][0]];
            }
            $pos = $start + strlen($full);
        }
        if ($pos < strlen($tpl)) {
            $tokens[] = ['text', substr($tpl, $pos)];
        }

        return $tokens;
    }

    /** Parse tokens into a node list until a closing/else token. */
    private static function parse(array $tokens, int &$i): array
    {
        $nodes = [];
        while ($i < count($tokens)) {
            $t = $tokens[$i];
            if ($t[0] === 'close' || $t[0] === 'else') {
                return $nodes;
            }
            $i++;
            if ($t[0] === 'open') {
                $body = self::parse($tokens, $i);
                $else = [];
                if (($tokens[$i][0] ?? null) === 'else') {
                    $i++;
                    $else = self::parse($tokens, $i);
                }
                $i++; // closing tag
                $nodes[] = [$t[1], $t[2], $body, $else];
            } else {
                $nodes[] = $t;
            }
        }

        return $nodes;
    }

    private static function lookup(string $name, array $stack): mixed
    {
        foreach ($stack as $frame) {
            if (is_array($frame) && array_key_exists($name, $frame)) {
                return $frame[$name];
            }
        }

        return null;
    }

    private static function truthy(mixed $v): bool
    {
        if (is_array($v)) {
            return $v !== [];
        }

        return ! in_array($v, [null, false, '', '0', 0, 0.0], true);
    }

    private static function str(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? '1' : '';
        }

        return is_scalar($v) ? (string) $v : (is_array($v) ? json_encode($v) : '');
    }

    private static function run(array $nodes, array $stack): string
    {
        $out = '';
        foreach ($nodes as $n) {
            switch ($n[0]) {
                case 'text':
                    $out .= $n[1];
                    break;
                case 'raw':
                    $out .= self::str(self::lookup($n[1], $stack));
                    break;
                case 'esc':
                    $name = $n[1];
                    if (str_starts_with($name, 'url:')) {
                        $out .= e(Url::safe(self::str(self::lookup(trim(substr($name, 4)), $stack))));
                    } elseif ($name === 'children' || $name === 'selector') {
                        $out .= self::str(self::lookup($name, $stack));
                    } else {
                        $out .= e(self::str(self::lookup($name, $stack)));
                    }
                    break;
                case 'each':
                    $items = self::lookup($n[1], $stack);
                    foreach (array_values(is_array($items) ? $items : []) as $idx => $item) {
                        $frame = is_array($item) ? $item : ['value' => $item];
                        $frame['@index'] = $idx;
                        $frame['@number'] = $idx + 1;
                        $out .= self::run($n[2], array_merge([$frame], $stack));
                    }
                    break;
                case 'if':
                    $out .= self::run(self::truthy(self::lookup($n[1], $stack)) ? $n[2] : $n[3], $stack);
                    break;
            }
        }

        return $out;
    }
}
