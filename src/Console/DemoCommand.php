<?php

declare(strict_types=1);

namespace Atlas\Console;

use Atlas\Atlas;
use Atlas\Enums\PageStatus;
use Atlas\Models\Page;
use Atlas\Templates\NodeFactory;
use Atlas\Templates\PortfolioTemplate;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'atlas:demo {--slug=demo : Slug of the sample page} {--force : Replace the page if it exists}';

    protected $description = 'Create a sample portfolio page that shows off the built-in blocks';

    public function handle(Atlas $atlas): int
    {
        $slug = (string) $this->option('slug');
        $existing = Page::where('slug', $slug)->first();

        if ($existing && ! $this->option('force')) {
            $this->error("A page with slug “{$slug}” already exists. Use --force to replace it.");

            return self::FAILURE;
        }
        $existing?->delete();

        $template = new PortfolioTemplate();
        $page = Page::create([
            'title' => 'Portfolio demo',
            'slug' => $slug,
            'status' => PageStatus::Published,
            'content' => $template->tree(new NodeFactory($atlas)),
            'meta' => $template->meta(),
        ]);

        $this->info('Demo page created: ' . $page->url());

        return self::SUCCESS;
    }
}
