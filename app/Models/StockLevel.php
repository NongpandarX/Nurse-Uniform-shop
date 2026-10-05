<?php

namespace App\Models;

use App\Models\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    use HasFactory, BranchScoped;

    protected $fillable = [
        'branch_id',
        'variant_id',
        'qty',
        'min_qty',
        'out_of_stock_alerted_at',
        'last_counted_at',
        'last_counted_by',
        'low_stock_alerted_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'min_qty' => 'integer',
        'out_of_stock_alerted_at' => 'datetime',
        'last_counted_at' => 'datetime',
        'low_stock_alerted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function lastCountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_counted_by');
    }

    public function isLowStock(): bool
    {
        return $this->qty > 0 && $this->qty <= $this->min_qty;
    }

    public function isOutOfStock(): bool
    {
        return $this->qty <= 0;
    }
}
