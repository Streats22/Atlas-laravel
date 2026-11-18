<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = ['name', 'design', 'html'];

    protected function casts(): array
    {
        return ['design' => 'array'];
    }
}
