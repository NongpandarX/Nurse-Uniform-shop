<?php

namespace App\Events;

use App\Models\StockLevel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OutOfStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StockLevel $stockLevel
    ) {}
}
