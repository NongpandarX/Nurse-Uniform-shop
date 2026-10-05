<?php

namespace App\Models;

use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockCount extends Model
{
    use HasFactory, BranchScoped;

    protected $fillable = [
        'branch_id',
        'count_date',
        'status',
        'revision',
        'started_by',
        'submitted_by',
        'submitted_at',
        'note',
    ];

    protected $casts = [
        'count_date' => 'date',
        'revision' => 'integer',
        'submitted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function dailyClosing(): HasOne
    {
        return $this->hasOne(DailyClosing::class);
    }
}
