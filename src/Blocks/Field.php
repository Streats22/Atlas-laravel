<?php

declare(strict_types=1);

namespace Atlas\Blocks;

use Illuminate\Support\Facades\Lang;

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

    public static function number(string $name, ?string $label = null, int|float|null $default = 0, array $extra = []): array
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

    /** Shorthand for translatable(). */
    public static function t(array $field): array
    {
        return self::translatable($field);
    }

    /** Mark a field as translatable: editors can enter a value per locale. */
    public static function translatable(array $field): array
    {
        $field['translatable'] = true;

        return $field;
    }

    /**
     * A list of items, each with its own sub-fields (portfolio items, slides, …).
     *
     * @param  array  $fields   Sub-fields built with Field::*
     * @param  array  $default  Default items
     * @param  string|null  $itemLabel  Sub-field whose value titles each row
     */
    public static function repeater(string $name, array $fields, ?string $label = null, array $default = [], ?string $itemLabel = null): array
    {
        return self::make('repeater', $name, $label, $default, [
            'fields' => array_values($fields),
            'item_label' => $itemLabel ?? ($fields[0]['name'] ?? null),
        ]);
    }

    /** Default values for a field list. */
    public static function defaults(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field['name']] = $field['default'] ?? null;
        }

        return $out;
    }

    /** Translate a field's label and option labels (lang keys: atlas::blocks.{type}.fields.*, atlas::fields.*, atlas::options.*). */
    public static function localize(array $field, ?string $blockType = null): array
    {
        $name = $field['name'];
        $keys = $blockType ? ["atlas::blocks.{$blockType}.fields.{$name}"] : [];
        $keys[] = "atlas::fields.{$name}";

        foreach ($keys as $key) {
            if (Lang::has($key)) {
                $field['label'] = __($key);
                break;
            }
        }

        foreach ($field['options'] ?? [] as $value => $text) {
            if (Lang::has("atlas::options.{$value}")) {
                $field['options'][$value] = __("atlas::options.{$value}");
            }
        }

        if (isset($field['fields'])) {
            $field['fields'] = array_map(fn ($f) => self::localize($f, $blockType), $field['fields']);
        }

        return $field;
    }
}
