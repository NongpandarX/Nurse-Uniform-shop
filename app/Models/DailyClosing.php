<?php

namespace App\Models;

use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use HasFactory, BranchScoped;

    protected $fillable = [
        'branch_id',
        'closing_date',
        'closed_by',
        'closed_at',
        'stock_count_id',
        'closing_submission_id',
        'low_stock_count',
        'staff_present',
        'note',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'closed_at' => 'datetime',
        'low_stock_count' => 'integer',
        'staff_present' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function closingSubmission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class, 'closing_submission_id');
    }
}
