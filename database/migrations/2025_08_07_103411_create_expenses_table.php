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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();

            $table->string('name');

            $table->decimal('amount',8,2);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'credit_card'])->default('cash');

            $table->string('note');
            $table->string('invoice_image')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('report_no')->nullable()->constrained('daily_reports')->nullOnDelete();

            $table->boolean('is_deported')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
