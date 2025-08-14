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
        Schema::create('input_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_no')->unique();

            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
           
            $table->date("date")->index();

            $table->decimal('value', 8, 2);
           
            $table->integer('quantity');
            
            $table->string('note');
           
            $table->enum('type', ['purchase', 'exchange', 'return'])->default('purchase');
            
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_invoices');
    }
};
