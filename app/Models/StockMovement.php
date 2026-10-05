<?php

namespace App\Models;

use App\Enums\StockReason;
use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory, BranchScoped;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'variant_id',
        'reason',
        'qty_before',
        'qty_change',
        'qty_after',
        'ref_type',
        'ref_id',
        'user_id',
        'note',
        'created_at',
    ];

    protected $casts = [
        'reason' => StockReason::class,
        'qty_before' => 'integer',
        'qty_change' => 'integer',
        'qty_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }
}
