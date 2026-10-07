<?php

namespace Atlas\Blocks;

use Atlas\Models\CustomBlock;
use Atlas\Rendering\Renderer;
use Atlas\Support\Template;
use Illuminate\Support\HtmlString;

/** A block defined in the database through the editor's block builder. */
class DbBlock extends Block
{
    public function __construct(public readonly CustomBlock $model) {}

    public function type(): string
    {
        return $this->model->type;
    }

    public function label(): string
    {
        return $this->model->label;
    }

    public function category(): string
    {
        return $this->model->category ?: 'Custom';
    }

    public function icon(): string
    {
        return $this->model->icon ?: '◇';
    }

    public function container(): bool
    {
        return (bool) $this->model->container;
    }

    public function fields(): array
    {
        return $this->model->fields ?? [];
    }

    public function toDefinition(): array
    {
        return array_merge(parent::toDefinition(), ['custom' => true, 'id' => $this->model->id]);
    }

    public function render(array $props, HtmlString $children, array $node, bool $editing): string
    {
        $domId = $node['dom_id'] ?? 'atlas-'.($node['id'] ?? '');
        $context = array_merge($props, [
            'children' => (string) $children,
            'id' => $domId,
            'selector' => '#'.$domId,
        ]);

        $html = Template::render((string) $this->model->html, $context);

        if (filled($this->model->css)) {
            $html .= '<style>'.Renderer::scopeCss((string) $this->model->css, $domId).'</style>';
        }

        if (! $editing && filled($this->model->js)) {
            $html .= '<script>(function(){var el=document.getElementById('.json_encode($domId).');'."\n"
                .$this->model->js."\n".'}).call(window);</script>';
        }

        return $html;
    }
}
