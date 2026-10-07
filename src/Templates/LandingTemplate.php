<?php

declare(strict_types=1);

namespace Atlas\Templates;

/** Hero → three features → call to action. */
final class LandingTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'landing';
    }

    public function tree(NodeFactory $n): array
    {
        $feature = fn (string $icon, string $title, string $text) => $n->make('icon-box', ['icon' => $icon, 'title' => $title, 'text' => $text, 'align' => 'left']);

        return [
            $n->make('hero'),
            $n->section([], [
                $n->make('heading', ['eyebrow' => 'Why us', 'text' => 'Everything you need', 'align' => 'center', 'anim' => 'fade-up']),
                $n->make('columns', ['layout' => '1fr 1fr 1fr'], array_map(fn ($cell) => $n->section(['padding_y' => 0, 'max_width' => 'full'], [$cell]), [
                    $feature('⚡', 'Fast by default', 'Pages render on the server and stay light on the client.'),
                    $feature('🎨', 'Make it yours', 'Light and dark themes, your fonts, your accent colour.'),
                    $feature('🌍', 'Speak every language', 'Translate any text and let visitors switch language.'),
                ])),
            ]),
            $n->section(['tone' => 'accent'], [
                $n->make('heading', ['text' => 'Ready when you are', 'align' => 'center']),
                $n->make('button', ['label' => 'Get started', 'url' => '#', 'align' => 'center', 'size' => 'lg']),
            ]),
        ];
    }
}
