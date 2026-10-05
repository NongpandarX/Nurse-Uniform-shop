<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('qty')->default(0);
            $table->integer('min_qty')->default(7);
            $table->timestamp('out_of_stock_alerted_at')->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->foreignId('last_counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
