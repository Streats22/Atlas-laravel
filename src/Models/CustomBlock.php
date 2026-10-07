<?php

declare(strict_types=1);

namespace Atlas\Models;

use Illuminate\Database\Eloquent\Model;

/** A block created in the editor's block builder (no PHP needed). */
class CustomBlock extends Model
{
    protected $table = 'atlas_blocks';

    protected $fillable = ['type', 'label', 'category', 'icon', 'container', 'fields', 'html', 'css', 'js'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'container' => 'boolean'];
    }

    /** Shape sent to the builder UI. */
    public function toBuilder(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'category' => $this->category,
            'icon' => $this->icon,
            'container' => (bool) $this->container,
            'fields' => $this->fields ?? [],
            'html' => $this->html,
            'css' => $this->css,
            'js' => $this->js,
        ];
    }
}
