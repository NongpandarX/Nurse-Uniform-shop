<?php

namespace App\Events;

use App\Models\DailyClosing;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DailyClosing $closing
    ) {}
}
