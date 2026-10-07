<?php

declare(strict_types=1);

namespace Atlas\Blocks\Builtin;

use Atlas\Blocks\Field;

class Video extends BuiltinBlock
{
    protected string $type = 'video';

    protected string $label = 'Video';

    protected string $icon = '▶';

    protected string $category = 'Content';

    public function fields(): array
    {
        return [
            Field::url('url', 'YouTube, Vimeo or video file URL'),
            Field::select('ratio', ['16/9' => '16:9', '4/3' => '4:3', '1/1' => 'Square', '21/9' => 'Cinematic'], 'Aspect ratio', '16/9'),
            Field::image('poster', 'Poster image (video files)'),
            Field::checkbox('autoplay', 'Autoplay (muted, video files)'),
            Field::checkbox('loop', 'Loop (video files)'),
            Field::t(Field::text('caption', 'Caption')),
        ];
    }

    public function data(array $props): array
    {
        $url = trim((string) ($props['url'] ?? ''));
        $embed = null;
        $file = null;

        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,15})~i', $url, $m)) {
            $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        } elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
            $embed = 'https://player.vimeo.com/video/' . $m[1];
        } elseif ($url !== '') {
            $file = \Atlas\Support\Url::safe($url);
        }

        return ['embed' => $embed, 'file' => $file];
    }
}
