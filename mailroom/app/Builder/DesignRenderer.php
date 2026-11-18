<?php

declare(strict_types=1);

namespace App\Builder;

/**
 * Turns a normalised design into table-based, inline-styled email HTML that holds up in Outlook and Gmail.
 */
final class DesignRenderer
{
    public function __construct(private readonly EmailDesign $design) {}

    /** @param  bool  $editor  Adds data-block attributes so the editor can map clicks in the preview to blocks. */
    public function render(mixed $design, bool $editor = false): string
    {
        $d = $this->design->normalize($design);
        $s = $d['settings'];
        $rows = '';
        foreach ($d['blocks'] as $block) {
            $attr = $editor ? ' data-block="'.e($block['id']).'"' : '';
            $rows .= '<tr><td'.$attr.' style="padding:'.$this->padding($block['type']).'">'.$this->block($block['type'], $block['props'], $s).'</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td style="padding:48px 24px;text-align:center;color:#9ca3af">Add blocks to start designing.</td></tr>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<style>body{margin:0}img{border:0;max-width:100%}a{color:'.$s['accent'].'}p{margin:0 0 12px}'
            .'@media (max-width:620px){.mr-col{display:block!important;width:100%!important}}'
            .($editor ? '[data-block]{cursor:pointer;outline:2px solid transparent;outline-offset:-2px}[data-block]:hover{outline-color:'.$s['accent'].'66}[data-block].mr-selected{outline-color:'.$s['accent'].'}' : '')
            .'</style></head>'
            .'<body style="margin:0;padding:0;background:'.$s['background'].'">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:'.$s['background'].'"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:'.$s['width'].'px;background:'.$s['content_background'].';border-radius:8px;font-family:'.e($s['font']).';color:'.$s['text_color'].';font-size:16px;line-height:1.6">'
            .$rows
            .'</table></td></tr></table></body></html>';
    }

    private function padding(string $type): string
    {
        return match ($type) {
            'image', 'spacer' => '0',
            'divider' => '8px 24px',
            default => '12px 24px',
        };
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<string, mixed>  $s
     */
    private function block(string $type, array $p, array $s): string
    {
        return match ($type) {
            'heading' => sprintf(
                '<h%1$d style="margin:0;text-align:%2$s;color:%3$s;font-size:%4$dpx;line-height:1.25">%5$s</h%1$d>',
                $p['level'], $p['align'], $p['color'] ?: $s['text_color'], [1 => 30, 2 => 24, 3 => 19][$p['level']], e($p['text']),
            ),
            'text' => '<div style="text-align:'.$p['align'].'">'.$p['html'].'</div>',
            'button' => $this->button($p, $s),
            'image' => $this->image($p),
            'columns' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
                .'<td class="mr-col" width="50%" valign="top" style="padding-right:12px">'.$p['left'].'</td>'
                .'<td class="mr-col" width="50%" valign="top" style="padding-left:12px">'.$p['right'].'</td></tr></table>',
            'divider' => '<div style="border-top:'.$p['thickness'].'px solid '.$p['color'].';font-size:0;line-height:0">&nbsp;</div>',
            'spacer' => '<div style="height:'.$p['height'].'px;font-size:0;line-height:0">&nbsp;</div>',
            'social' => '<div style="text-align:'.$p['align'].'">'.implode(' &nbsp;·&nbsp; ', array_map(
                fn (string $key, string $url) => '<a href="'.e($url).'" style="color:'.$s['accent'].';text-decoration:none;font-weight:bold">'.e(EmailDesign::SOCIAL[$key]).'</a>',
                array_keys($p['links']), $p['links'],
            )).'</div>',
            'footer' => '<div style="text-align:'.$p['align'].';font-size:12px;color:#6b7280">'.$p['html'].'</div>',
            default => '',
        };
    }

    /** @param  array<string, mixed>  $p */
    private function button(array $p, array $s): string
    {
        $bg = $p['color'] ?: $s['accent'];
        $width = $p['full_width'] ? 'display:block;' : 'display:inline-block;';

        return '<div style="text-align:'.$p['align'].'"><a href="'.e($p['url'] ?: '#').'" style="'.$width
            .'background:'.$bg.';color:'.$p['text_color'].';padding:13px 24px;border-radius:6px;text-decoration:none;font-weight:bold;text-align:center">'
            .e($p['label']).'</a></div>';
    }

    /** @param  array<string, mixed>  $p */
    private function image(array $p): string
    {
        if ($p['src'] === '') {
            return '<div style="background:#e5e7eb;color:#6b7280;text-align:center;padding:48px 12px;font-size:14px">Image: choose or upload one</div>';
        }
        $img = '<img src="'.e($p['src']).'" alt="'.e($p['alt']).'" width="'.$p['width'].'%" style="display:inline-block;width:'.$p['width'].'%;height:auto">';
        if ($p['url'] !== '') {
            $img = '<a href="'.e($p['url']).'">'.$img.'</a>';
        }

        return '<div style="text-align:'.$p['align'].';font-size:0;line-height:0">'.$img.'</div>';
    }
}
