<?php

namespace App\Models;

use App\Enums\ChecklistStatus;
use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSubmission extends Model
{
    use HasFactory, BranchScoped;

    protected $fillable = [
        'branch_id',
        'template_id',
        'check_date',
        'submitted_by',
        'submitted_at',
        'status',
        'score',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'check_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'status' => ChecklistStatus::class,
        'score' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistSubmissionItem::class, 'submission_id');
    }
}
