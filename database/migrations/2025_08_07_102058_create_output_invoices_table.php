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
        Schema::create('output_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_no')->unique();

            $table->date("date")->index();
            
            $table->decimal('value', 8, 2);
            $table->integer('quantity');

            $table->string('note')->default('لايوجد');

            $table->decimal('discount', 8, 2);
            $table->enum('discount_type', ['percentage', 'fixed']);

            $table->enum('type', ['sell', 'exchange', 'return'])->default(value: 'sell');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_deported')->default(false);
            
            $table->foreignId('report_no')->nullable()->constrained('daily_reports')->nullOnDelete();

            $table->index(['date', 'user_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('output_invoices');
    }
};
