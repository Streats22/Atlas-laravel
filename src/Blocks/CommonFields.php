<?php

declare(strict_types=1);

namespace Atlas\Blocks;

/** Properties every block gets in the inspector's "Advanced" section. */
class CommonFields
{
    public static function all(): array
    {
        $fields = [
            Field::select('anim', [
                'none' => 'None', 'fade' => 'Fade in', 'fade-up' => 'Fade up', 'fade-down' => 'Fade down',
                'fade-left' => 'Slide from right', 'fade-right' => 'Slide from left', 'zoom-in' => 'Zoom in',
                'zoom-out' => 'Zoom out', 'flip' => 'Flip', 'blur' => 'Blur in',
            ], 'Entrance animation', 'none'),
            Field::number('anim_duration', 'Animation duration (ms)', 700, ['min' => 100, 'max' => 5000, 'step' => 50]),
            Field::number('anim_delay', 'Animation delay (ms)', 0, ['min' => 0, 'max' => 5000, 'step' => 50]),
            Field::select('anim_hover', ['none' => 'None', 'lift' => 'Lift', 'zoom' => 'Zoom', 'glow' => 'Glow'], 'Hover effect', 'none'),
            Field::select('visibility', ['all' => 'Everywhere', 'hide-mobile' => 'Hide on mobile', 'hide-desktop' => 'Hide on desktop'], 'Visibility', 'all'),
            Field::number('margin_top', 'Margin top (px)', 0),
            Field::number('margin_bottom', 'Margin bottom (px)', 0),
            Field::text('css_class', 'CSS classes', ''),
        ];

        if (config('atlas.custom_code')) {
            $fields[] = Field::text('html_id', 'HTML id', '');
            $fields[] = Field::code('custom_css', 'Custom CSS  ({{selector}} = this block)', '', 'css');
        }

        return $fields;
    }

    public static function defaults(): array
    {
        return Field::defaults(self::all());
    }

    public static function definitions(): array
    {
        return array_map(fn ($f) => Field::localize($f), self::all());
    }
}
