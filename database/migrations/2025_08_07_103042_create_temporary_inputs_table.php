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
        Schema::create('temporary_inputs', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', length: 50)->unique();

            $table->string('product_no', length: 50);
            $table->string('product_name', length: 50);

            $table->decimal('buy_price',8,2);
            $table->decimal('sell_price',8,2);
            
            $table->unsignedInteger('quantity');

            $table->string('image')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_inputs');
    }
};
