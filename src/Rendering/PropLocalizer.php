<?php

declare(strict_types=1);

namespace Atlas\Rendering;

/**
 * Picks the current locale's value for every translatable field.
 * Translations live beside the default value as "name@locale".
 */
final class PropLocalizer
{
    public function localize(array $fields, array $props, string $locale, string $default): array
    {
        foreach ($fields as $field) {
            $name = $field['name'];

            if (($field['type'] ?? '') === 'repeater' && is_array($props[$name] ?? null)) {
                $props[$name] = array_map(
                    fn ($item) => is_array($item) ? $this->localize($field['fields'] ?? [], $item, $locale, $default) : $item,
                    array_values($props[$name]),
                );
            } elseif (! empty($field['translatable']) && $locale !== $default) {
                $translated = $props[$name . '@' . $locale] ?? null;
                if (is_string($translated) && $translated !== '') {
                    $props[$name] = $translated;
                }
            }
        }

        return $props;
    }
}
