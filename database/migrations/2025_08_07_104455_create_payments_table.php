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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['inside', 'outside']);
            $table->enum('method', ['cash', 'card', 'click']);
            
            $table->decimal('amount', 8, 2);
            $table->decimal('amount_paid', 8, 2);
            $table->decimal('change', 8, 2)->default(0);
            
            $table->foreignId('input_invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('output_invoice_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('report_no')->nullable();

            $table->boolean('is_deported')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
