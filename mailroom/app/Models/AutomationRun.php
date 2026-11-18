<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    /** Mirror the column defaults so new models behave like freshly loaded ones. */
    protected $attributes = ['status' => 'active', 'position' => 0];

    protected $fillable = ['contact_id', 'position', 'status', 'next_run_at', 'completed_at'];

    protected function casts(): array
    {
        return ['next_run_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
