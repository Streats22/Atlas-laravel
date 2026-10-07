<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Atlas;
use Atlas\Models\Page;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'atlas:demo {--slug=demo : Slug of the sample page} {--force : Replace the page if it exists}';

    protected $description = 'Create a sample portfolio page that shows off the built-in blocks';

    protected int $n = 0;

    protected Atlas $atlas;

    /** Build a node from a block's defaults, overriding props. */
    protected function n(string $type, array $props = [], array $children = []): array
    {
        $tpl = $this->atlas->blocks()->get($type)->toDefinition()['template'];

        return [
            'id' => 'd' . str_pad((string) ++$this->n, 3, '0', STR_PAD_LEFT),
            'type' => $type,
            'props' => array_merge((array) $tpl['props'], $props),
            'children' => $children ?: array_map(fn ($c) => ['id' => 'd' . str_pad((string) ++$this->n, 3, '0', STR_PAD_LEFT), 'type' => $c['type'], 'props' => array_merge((array) $this->atlas->blocks()->get($c['type'])->toDefinition()['template']['props'], (array) ($c['props'] ?? [])), 'children' => []], $tpl['children']),
        ];
    }

    public function handle(Atlas $atlas): int
    {
        $this->atlas = $atlas;
        $slug = (string) $this->option('slug');

        if ($existing = Page::where('slug', $slug)->first()) {
            if (! $this->option('force')) {
                $this->error("A page with slug “{$slug}” already exists. Use --force to replace it.");

                return self::FAILURE;
            }
            $existing->delete();
        }

        $section = fn (array $props, array $kids) => $this->n('section', $props, $kids);

        $work = [
            ['title' => 'Aurora Brand System', 'category' => 'Branding', 'description' => 'Identity, motion and a design system for a climate startup.', 'tags' => 'Branding, Motion', 'url' => '', 'image' => ''],
            ['title' => 'Northwind Storefront', 'category' => 'Web', 'description' => 'A headless commerce site with sub-second page loads.', 'tags' => 'Laravel, UX', 'url' => '', 'image' => ''],
            ['title' => 'Pulse Fitness App', 'category' => 'App', 'description' => 'Concept to prototype for a coaching app.', 'tags' => 'UI, Prototype', 'url' => '', 'image' => ''],
            ['title' => 'Atlas Annual Report', 'category' => 'Print', 'description' => 'A 120-page editorial design.', 'tags' => 'Editorial', 'url' => '', 'image' => ''],
            ['title' => 'Lumen Dashboard', 'category' => 'Web', 'description' => 'Analytics for 40k daily users.', 'tags' => 'Dashboard, Charts', 'url' => '', 'image' => ''],
            ['title' => 'Mono Packaging', 'category' => 'Branding', 'description' => 'Sustainable packaging range.', 'tags' => 'Print, 3D', 'url' => '', 'image' => ''],
        ];

        $tree = [
            $this->n('hero', [
                'eyebrow' => 'Portfolio 2026', 'eyebrow@nl' => 'Portfolio 2026',
                'title' => 'Designer & developer who ships', 'title@nl' => 'Ontwerper & ontwikkelaar die oplevert',
                'text' => 'I help ambitious teams turn ideas into fast, beautiful products.', 'text@nl' => 'Ik help ambitieuze teams om ideeën om te zetten in snelle, mooie producten.',
                'primary_label' => 'See my work', 'primary_label@nl' => 'Bekijk mijn werk', 'primary_url' => '#work',
                'secondary_label' => 'Get in touch', 'secondary_label@nl' => 'Neem contact op', 'secondary_url' => '#contact',
            ]),
            $section(['max_width' => 'normal'], [
                $this->n('typewriter', ['prefix' => 'I design', 'prefix@nl' => 'Ik ontwerp', 'words' => "brands\nwebsites\napps\nexperiences", 'words@nl' => "merken\nwebsites\napps\nbelevingen", 'suffix' => '.', 'anim' => 'fade-up']),
            ]),
            $section(['tone' => 'surface'], [
                $this->n('counter', ['anim' => 'fade-up']),
            ]),
            $section([], [
                $this->n('heading', ['eyebrow' => 'Selected work', 'eyebrow@nl' => 'Uitgelicht werk', 'text' => 'Projects I am proud of', 'text@nl' => 'Projecten waar ik trots op ben', 'level' => 'h2', 'align' => 'center', 'anim' => 'fade-up', 'html_id' => 'work']),
                $this->n('portfolio-grid', ['items' => $work, 'all_label' => 'All', 'all_label@nl' => 'Alles', 'anim' => 'fade-up', 'style' => 'overlay']),
            ]),
            $section(['tone' => 'surface'], [
                $this->n('project-showcase', [
                    'title' => 'Aurora Brand System', 'summary' => 'A complete identity for a climate-tech startup.', 'summary@nl' => 'Een complete identiteit voor een climate-tech startup.',
                    'description' => "The brief: **look credible to investors and approachable to families**.\n\n- Logo, colour & type\n- Motion principles\n- A coded component library",
                    'image_side' => 'right', 'anim' => 'fade-up',
                ]),
            ]),
            $section([], [
                $this->n('columns', ['layout' => '1fr 1fr', 'gap' => 48], [
                    $section(['padding_y' => 0, 'max_width' => 'full'], [
                        $this->n('heading', ['text' => 'Skills', 'text@nl' => 'Vaardigheden', 'level' => 'h3']),
                        $this->n('skills', ['items' => [['name' => 'Brand & identity', 'level' => 92], ['name' => 'Laravel & PHP', 'level' => 88], ['name' => 'Motion design', 'level' => 74], ['name' => 'Accessibility', 'level' => 81]]]),
                    ]),
                    $section(['padding_y' => 0, 'max_width' => 'full'], [
                        $this->n('heading', ['text' => 'Experience', 'text@nl' => 'Ervaring', 'level' => 'h3']),
                        $this->n('timeline'),
                    ]),
                ]),
            ]),
            $section(['tone' => 'inverted'], [
                $this->n('marquee', ['items' => [['text' => 'Branding'], ['text' => 'Web'], ['text' => 'Apps'], ['text' => 'Motion'], ['text' => 'Print']], 'size' => 'xl', 'speed' => 28]),
            ]),
            $section([], [
                $this->n('heading', ['text' => 'Kind words', 'text@nl' => 'Lovende woorden', 'align' => 'center']),
                $this->n('testimonials', ['layout' => 'carousel']),
            ]),
            $section(['tone' => 'surface', 'max_width' => 'narrow'], [
                $this->n('heading', ['text' => 'Questions', 'text@nl' => 'Vragen', 'align' => 'center']),
                $this->n('accordion', ['items' => [
                    ['title' => 'Are you available for new projects?', 'title@nl' => 'Ben je beschikbaar voor nieuwe projecten?', 'text' => 'Yes — from next month.', 'text@nl' => 'Ja — vanaf volgende maand.'],
                    ['title' => 'How do we start?', 'title@nl' => 'Hoe beginnen we?', 'text' => 'A 30 minute call to understand your goals.', 'text@nl' => 'Een gesprek van 30 minuten om je doelen te begrijpen.'],
                ]]),
            ]),
            $section(['tone' => 'accent', 'html_id' => 'contact'], [
                $this->n('heading', ['text' => "Let's build something great", 'text@nl' => 'Laten we iets moois bouwen', 'align' => 'center', 'level' => 'h2']),
                $this->n('button', ['label' => 'hello@example.com', 'url' => 'mailto:hello@example.com', 'align' => 'center', 'size' => 'lg', 'anim' => 'zoom-in']),
                $this->n('social-links', ['align' => 'center']),
            ]),
            $section(['padding_y' => 24], [
                $this->n('columns', ['layout' => '1fr 1fr', 'align' => 'center'], [
                    $section(['padding_y' => 0, 'max_width' => 'full'], [$this->n('text', ['text' => '© 2026 Your Name', 'size' => 'sm'])]),
                    $section(['padding_y' => 0, 'max_width' => 'full'], [$this->n('language-switcher'), $this->n('theme-toggle')]),
                ]),
            ]),
        ];

        $page = Page::create([
            'title' => 'Portfolio demo', 'slug' => $slug, 'status' => 'published', 'content' => $tree,
            'meta' => ['description' => 'A sample portfolio built with Atlas.', 'theme' => 'auto', 'theme_toggle' => true],
        ]);

        $this->info('Demo page created: ' . $page->url());

        return self::SUCCESS;
    }
}
