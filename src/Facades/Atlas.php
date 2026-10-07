<?php

declare(strict_types=1);

namespace Atlas\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Atlas\Blocks\BlockRegistry blocks()
 * @method static \Atlas\Rendering\Renderer renderer()
 * @method static \Atlas\Atlas block(\Atlas\Blocks\Block|string $block)
 * @method static \Atlas\Atlas viewBlock(string $type, string $view, ?string $label = null, array $fields = [], string $category = 'Custom', string $icon = '▢', bool $container = false)
 * @method static \Atlas\Templates\TemplateRegistry templates()
 * @method static \Atlas\Atlas template(\Atlas\Templates\PageTemplate|string $template)
 * @method static \Atlas\Atlas style(string $url)
 * @method static \Atlas\Atlas script(string $url, bool $defer = false)
 * @method static \Atlas\Atlas auth(\Closure $callback)
 * @method static \Illuminate\Support\HtmlString page(string $slug)
 *
 * @see \Atlas\Atlas
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Atlas\Atlas::class;
    }
}
