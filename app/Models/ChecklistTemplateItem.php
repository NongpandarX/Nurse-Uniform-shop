<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistTemplateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'label',
        'require_photo',
        'sort',
    ];

    protected $casts = [
        'require_photo' => 'boolean',
        'sort' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function submissionItems(): HasMany
    {
        return $this->hasMany(ChecklistSubmissionItem::class, 'template_item_id');
    }
}
