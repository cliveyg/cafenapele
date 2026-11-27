<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cafenapele_pos_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->primary();
            $table->unsignedBigInteger('dining_table_id')->nullable()->index();
            $table->unsignedBigInteger('version')->default(1);
            $table->uuid('draft_token')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cafenapele_pos_orders');
    }
};
