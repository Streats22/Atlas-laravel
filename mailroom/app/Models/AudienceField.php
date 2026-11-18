<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class AudienceField extends Model
{
    protected $fillable = ['label', 'key', 'type', 'options', 'required', 'show_on_form', 'position'];

    protected function casts(): array
    {
        return ['type' => FieldType::class, 'options' => 'array', 'required' => 'boolean', 'show_on_form' => 'boolean'];
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    /** @return list<mixed> */
    public function rules(bool $enforceRequired = true): array
    {
        $rules = [$enforceRequired && $this->required ? 'required' : 'nullable', ...$this->type->rules()];
        if ($this->type === FieldType::Dropdown) {
            $rules[] = Rule::in($this->options ?? []);
        }

        return $rules;
    }
}
