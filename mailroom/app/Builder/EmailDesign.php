<?php

declare(strict_types=1);

namespace App\Builder;

use Illuminate\Support\Str;

/**
 * Normalises a builder design (settings + blocks) so that only known block types and
 * whitelisted values survive. Anything stored or rendered goes through here first.
 */
final class EmailDesign
{
    public const BLOCKS = [
        'heading' => 'Heading',
        'text' => 'Text',
        'button' => 'Button',
        'image' => 'Image',
        'columns' => 'Two columns',
        'divider' => 'Divider',
        'spacer' => 'Spacer',
        'social' => 'Social links',
        'footer' => 'Footer',
    ];

    /** Blocks only available on landing pages (forms don't work inside email clients). */
    public const PAGE_BLOCKS = ['signup' => 'Signup form'];

    /** @return array<string, string> */
    public static function blocks(bool $landing = false): array
    {
        return $landing ? self::BLOCKS + self::PAGE_BLOCKS : self::BLOCKS;
    }

    public const SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];

    private const ALIGN = ['left', 'center', 'right'];

    private const FONTS = ['Helvetica, Arial, sans-serif', 'Georgia, serif', '"Trebuchet MS", sans-serif', 'Verdana, sans-serif', '"Courier New", monospace'];

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /** @return array{settings: array<string, mixed>, blocks: list<array{id:string, type:string, props:array<string, mixed>}>} */
    public function normalize(mixed $design, bool $landing = false): array
    {
        $allowed = self::blocks($landing);
        $design = is_array($design) ? $design : [];
        $s = is_array($design['settings'] ?? null) ? $design['settings'] : [];
        $blocks = [];
        foreach (array_slice(is_array($design['blocks'] ?? null) ? $design['blocks'] : [], 0, 200) as $block) {
            if (is_array($block) && isset($allowed[$block['type'] ?? ''])) {
                $blocks[] = [
                    'id' => is_string($block['id'] ?? null) && preg_match('/^[a-zA-Z0-9_-]{1,40}$/', $block['id']) ? $block['id'] : Str::random(8),
                    'type' => $block['type'],
                    'props' => $this->props($block['type'], is_array($block['props'] ?? null) ? $block['props'] : []),
                ];
            }
        }

        return [
            'settings' => [
                'background' => Values::color($s['background'] ?? null, '#f4f4f5'),
                'content_background' => Values::color($s['content_background'] ?? null, '#ffffff'),
                'text_color' => Values::color($s['text_color'] ?? null, '#1f2937'),
                'accent' => Values::color($s['accent'] ?? null, '#4f46e5'),
                'font' => Values::pick($s['font'] ?? null, self::FONTS, self::FONTS[0]),
                'width' => Values::int($s['width'] ?? null, 480, 800, 600),
            ],
            'blocks' => $blocks,
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(string $type): array
    {
        return $this->props($type, match ($type) {
            'heading' => ['text' => 'Your headline here', 'level' => 1],
            'text' => ['html' => '<p>Hi {{ first_name | there }}, write something people want to read.</p>'],
            'button' => ['label' => 'Call to action', 'url' => 'https://example.com'],
            'image' => ['src' => '', 'alt' => '', 'width' => 100],
            'columns' => ['left' => '<p>Left column</p>', 'right' => '<p>Right column</p>'],
            'social' => ['links' => ['facebook' => 'https://facebook.com', 'instagram' => 'https://instagram.com']],
            'footer' => ['html' => '<p>Your company · Street 1 · City</p>'],
            'signup' => ['button' => 'Subscribe', 'show_name' => true, 'note' => 'We respect your privacy. Unsubscribe at any time.'],
            default => [],
        });
    }

    /** @return array{settings: array<string, mixed>, blocks: list<array<string, mixed>>} */
    public function starter(bool $landing = false): array
    {
        $types = $landing ? ['heading', 'text', 'signup', 'divider', 'footer'] : ['image', 'heading', 'text', 'button', 'divider', 'footer'];

        return $this->normalize(['blocks' => array_map(
            fn (string $type) => ['type' => $type, 'props' => $landing && $type === 'text'
                ? ['html' => '<p>Tell visitors what they get when they sign up, and how often you will email them.</p>']
                : $this->defaults($type)],
            $types,
        )], $landing);
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    private function props(string $type, array $p): array
    {
        $align = Values::pick($p['align'] ?? null, self::ALIGN, $type === 'text' || $type === 'heading' ? 'left' : 'center');

        return match ($type) {
            'heading' => ['text' => Values::text($p['text'] ?? '', 300), 'level' => Values::int($p['level'] ?? 1, 1, 3, 1), 'align' => $align, 'color' => Values::color($p['color'] ?? null, '')],
            'text' => ['html' => $this->sanitizer->clean(Values::text($p['html'] ?? '', 20000)), 'align' => $align],
            'button' => [
                'label' => Values::text($p['label'] ?? '', 120), 'url' => Values::url($p['url'] ?? ''), 'align' => $align,
                'color' => Values::color($p['color'] ?? null, ''), 'text_color' => Values::color($p['text_color'] ?? null, '#ffffff'),
                'full_width' => (bool) ($p['full_width'] ?? false),
            ],
            'image' => [
                'src' => Values::url($p['src'] ?? ''), 'alt' => Values::text($p['alt'] ?? '', 200), 'url' => Values::url($p['url'] ?? ''),
                'width' => Values::int($p['width'] ?? 100, 10, 100, 100), 'align' => $align,
            ],
            'columns' => ['left' => $this->sanitizer->clean(Values::text($p['left'] ?? '', 10000)), 'right' => $this->sanitizer->clean(Values::text($p['right'] ?? '', 10000))],
            'divider' => ['color' => Values::color($p['color'] ?? null, '#e5e7eb'), 'thickness' => Values::int($p['thickness'] ?? 1, 1, 8, 1)],
            'spacer' => ['height' => Values::int($p['height'] ?? 24, 4, 200, 24)],
            'social' => [
                'links' => array_filter(array_map(
                    fn ($u) => Values::url($u),
                    array_intersect_key(is_array($p['links'] ?? null) ? $p['links'] : [], self::SOCIAL),
                )),
                'align' => $align,
            ],
            'signup' => [
                'button' => Values::text($p['button'] ?? 'Subscribe', 60) ?: 'Subscribe',
                'show_name' => (bool) ($p['show_name'] ?? true),
                'note' => Values::text($p['note'] ?? '', 300),
            ],
            'footer' => ['html' => $this->sanitizer->clean(Values::text($p['html'] ?? '', 5000)), 'align' => Values::pick($p['align'] ?? null, self::ALIGN, 'center')],
            default => [],
        };
    }
}
