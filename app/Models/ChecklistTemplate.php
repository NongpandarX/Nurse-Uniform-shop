<?php

namespace App\Models;

use App\Enums\ChecklistType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'is_active',
    ];

    protected $casts = [
        'type' => ChecklistType::class,
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class, 'template_id')->orderBy('sort');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChecklistSubmission::class, 'template_id');
    }
}
