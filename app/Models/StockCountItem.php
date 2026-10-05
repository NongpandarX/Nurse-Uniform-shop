<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_count_id',
        'variant_id',
        'prev_qty',
        'counted_qty',
        'diff',
        'is_confirmed',
        'counted_by',
        'reason',
    ];

    protected $casts = [
        'prev_qty' => 'integer',
        'counted_qty' => 'integer',
        'diff' => 'integer',
        'is_confirmed' => 'boolean',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
