<?php

declare(strict_types=1);

namespace Atlas\Templates;

/** A complete portfolio page that exercises most built-in blocks (also used by `atlas:demo`). */
final class PortfolioTemplate extends PageTemplate
{
    public function key(): string
    {
        return 'portfolio';
    }

    public function meta(): array
    {
        return ['description' => 'A sample portfolio built with Atlas.', 'theme' => 'auto', 'theme_toggle' => true];
    }

    public function tree(NodeFactory $n): array
    {
        $projects = [
            ['Aurora Brand System', 'Branding', 'Identity, motion and a design system for a climate startup.', 'Branding, Motion'],
            ['Northwind Storefront', 'Web', 'A headless commerce site with sub-second page loads.', 'Laravel, UX'],
            ['Pulse Fitness App', 'App', 'Concept to prototype for a coaching app.', 'UI, Prototype'],
            ['Atlas Annual Report', 'Print', 'A 120-page editorial design.', 'Editorial'],
            ['Lumen Dashboard', 'Web', 'Analytics for 40k daily users.', 'Dashboard, Charts'],
            ['Mono Packaging', 'Branding', 'Sustainable packaging range.', 'Print, 3D'],
        ];
        $work = [];
        foreach ($projects as $i => [$title, $category, $description, $tags]) {
            $work[] = ['image' => $n->image($i + 1), 'title' => $title, 'category' => $category, 'description' => $description, 'tags' => $tags, 'url' => ''];
        }

        return [
            $n->make('hero', [
                'eyebrow' => 'Portfolio 2026', 'eyebrow@nl' => 'Portfolio 2026',
                'title' => 'Designer & developer who ships', 'title@nl' => 'Ontwerper & ontwikkelaar die oplevert',
                'text' => 'I help ambitious teams turn ideas into fast, beautiful products.', 'text@nl' => 'Ik help ambitieuze teams om ideeën om te zetten in snelle, mooie producten.',
                'primary_label' => 'See my work', 'primary_label@nl' => 'Bekijk mijn werk', 'primary_url' => '#work',
                'secondary_label' => 'Get in touch', 'secondary_label@nl' => 'Neem contact op', 'secondary_url' => '#contact',
            ]),
            $n->section(['max_width' => 'normal'], [
                $n->make('typewriter', ['prefix' => 'I design', 'prefix@nl' => 'Ik ontwerp', 'words' => "brands\nwebsites\napps\nexperiences", 'words@nl' => "merken\nwebsites\napps\nbelevingen", 'suffix' => '.', 'anim' => 'fade-up']),
            ]),
            $n->section(['tone' => 'surface'], [$n->make('counter', ['anim' => 'fade-up'])]),
            $n->section([], [
                $n->make('heading', ['eyebrow' => 'Selected work', 'eyebrow@nl' => 'Uitgelicht werk', 'text' => 'Projects I am proud of', 'text@nl' => 'Projecten waar ik trots op ben', 'align' => 'center', 'anim' => 'fade-up', 'html_id' => 'work']),
                $n->make('portfolio-grid', ['items' => $work, 'all_label@nl' => 'Alles', 'anim' => 'fade-up']),
            ]),
            $n->section(['tone' => 'surface'], [
                $n->make('project-showcase', [
                    'image' => $n->image(1), 'title' => 'Aurora Brand System', 'summary' => 'A complete identity for a climate-tech startup.', 'summary@nl' => 'Een complete identiteit voor een climate-tech startup.',
                    'description' => "The brief: **look credible to investors and approachable to families**.\n\n- Logo, colour & type\n- Motion principles\n- A coded component library",
                    'image_side' => 'right', 'anim' => 'fade-up',
                ]),
            ]),
            $n->section([], [
                $n->make('heading', ['eyebrow' => 'Gallery', 'eyebrow@nl' => 'Galerij', 'text' => 'Details & moments', 'text@nl' => 'Details & momenten', 'align' => 'center', 'anim' => 'fade-up']),
                $n->make('gallery', ['columns' => '4', 'ratio' => '1/1', 'items' => array_map(
                    fn (int $i) => ['image' => $n->image($i), 'alt' => "Study {$i}", 'caption' => ''],
                    [5, 7, 8, 3],
                )]),
            ]),
            $n->section([], [
                $n->columns([
                    [
                        $n->make('heading', ['text' => 'Skills', 'text@nl' => 'Vaardigheden', 'level' => 'h3']),
                        $n->make('skills', ['items' => [['name' => 'Brand & identity', 'level' => 92], ['name' => 'Laravel & PHP', 'level' => 88], ['name' => 'Motion design', 'level' => 74], ['name' => 'Accessibility', 'level' => 81]]]),
                    ],
                    [
                        $n->make('heading', ['text' => 'Experience', 'text@nl' => 'Ervaring', 'level' => 'h3']),
                        $n->make('timeline'),
                    ],
                ], ['gap' => 48]),
            ]),
            $n->section(['tone' => 'inverted', 'padding_y' => 40], [
                $n->make('marquee', ['items' => [['text' => 'Branding'], ['text' => 'Web'], ['text' => 'Apps'], ['text' => 'Motion'], ['text' => 'Print']], 'size' => 'xl', 'speed' => 28]),
            ]),
            $n->section([], [
                $n->make('heading', ['text' => 'Kind words', 'text@nl' => 'Lovende woorden', 'align' => 'center']),
                $n->make('testimonials', ['layout' => 'carousel']),
            ]),
            $n->section(['tone' => 'surface', 'max_width' => 'narrow'], [
                $n->make('heading', ['text' => 'Questions', 'text@nl' => 'Vragen', 'align' => 'center']),
                $n->make('accordion', ['items' => [
                    ['title' => 'Are you available for new projects?', 'title@nl' => 'Ben je beschikbaar voor nieuwe projecten?', 'text' => 'Yes — from next month.', 'text@nl' => 'Ja — vanaf volgende maand.'],
                    ['title' => 'How do we start?', 'title@nl' => 'Hoe beginnen we?', 'text' => 'A 30 minute call to understand your goals.', 'text@nl' => 'Een gesprek van 30 minuten om je doelen te begrijpen.'],
                ]]),
            ]),
            $n->section(['tone' => 'accent', 'html_id' => 'contact'], [
                $n->make('heading', ['text' => "Let's build something great", 'text@nl' => 'Laten we iets moois bouwen', 'align' => 'center']),
                $n->make('button', ['label' => 'hello@example.com', 'url' => 'mailto:hello@example.com', 'align' => 'center', 'size' => 'lg', 'anim' => 'zoom-in']),
                $n->make('social-links', ['align' => 'center']),
            ]),
            $n->section(['padding_y' => 24], [
                $n->columns([
                    [$n->make('text', ['text' => '© 2026 Your Name', 'size' => 'sm'])],
                    [$n->make('language-switcher'), $n->make('theme-toggle')],
                ], ['align' => 'center']),
            ]),
        ];
    }
}
