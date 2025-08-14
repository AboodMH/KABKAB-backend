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
        Schema::create('company_payments', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index(); 

            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->decimal('amount',8,2);
            
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card', 'cheque'])->default('cash');
            
            $table->string('note');

            $table->index(['company_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_payments');
    }
};
