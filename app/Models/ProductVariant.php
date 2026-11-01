<?php

namespace App\Models;

use App\Enums\Size;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'option',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'size' => Size::class,
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'variant_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'variant_id');
    }

    public function stockCountItems(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'variant_id');
    }

    /**
     * ชื่อสินค้าแบบสมบูรณ์พร้อมตัวเลือกและไซส์
     * เช่น "กรุงเทพ ยาว (SSS)", "กางเกง (normal S)", "เข็มขัด"
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->product?->name ?? 'สินค้า';
        $details = [];

        if ($this->option) {
            $details[] = $this->option;
        }

        if ($this->size) {
            $details[] = $this->size->value ?? (string) $this->size;
        }

        if (!empty($details)) {
            $name .= ' (' . implode(' ', $details) . ')';
        }

        return $name;
    }
}
