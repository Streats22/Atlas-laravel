<?php

namespace Atlas\Blocks;

/**
 * Tiny helpers that describe the inspector inputs of a block.
 */
class Field
{
    public static function make(string $type, string $name, ?string $label = null, mixed $default = null, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'name' => $name,
            'label' => $label ?? ucfirst(str_replace(['_', '-'], ' ', $name)),
            'default' => $default,
        ], $extra);
    }

    public static function text(string $name, ?string $label = null, string $default = ''): array
    {
        return self::make('text', $name, $label, $default);
    }

    public static function textarea(string $name, ?string $label = null, string $default = ''): array
    {
        return self::make('textarea', $name, $label, $default);
    }

    public static function number(string $name, ?string $label = null, int|float $default = 0, array $extra = []): array
    {
        return self::make('number', $name, $label, $default, $extra);
    }

    public static function color(string $name, ?string $label = null, string $default = ''): array
    {
        return self::make('color', $name, $label, $default);
    }

    public static function checkbox(string $name, ?string $label = null, bool $default = false): array
    {
        return self::make('checkbox', $name, $label, $default);
    }

    /** @param array<string,string>|list<string> $options value => label */
    public static function select(string $name, array $options, ?string $label = null, ?string $default = null): array
    {
        if (array_is_list($options)) {
            $options = array_combine($options, $options);
        }

        return self::make('select', $name, $label, $default ?? array_key_first($options), ['options' => $options]);
    }

    public static function url(string $name, ?string $label = null, string $default = ''): array
    {
        return self::make('text', $name, $label, $default, ['placeholder' => 'https://']);
    }

    public static function image(string $name, ?string $label = null, string $default = ''): array
    {
        return self::make('image', $name, $label, $default);
    }

    /** A monospace editor. $language is a hint: html, css, js, blade, ... */
    public static function code(string $name, ?string $label = null, string $default = '', string $language = 'html'): array
    {
        return self::make('code', $name, $label, $default, ['language' => $language]);
    }
}
