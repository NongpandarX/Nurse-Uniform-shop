<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChecklistSubmissionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'template_item_id',
        'is_ok',
        'note',
    ];

    protected $casts = [
        'is_ok' => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class, 'submission_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateItem::class, 'template_item_id');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
}
