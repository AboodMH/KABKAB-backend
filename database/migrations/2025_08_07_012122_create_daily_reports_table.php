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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();

            $table->enum('shift', ['morning', 'evening', 'full'])->default('full');

            $table->decimal('cash', 10, 2);
            $table->decimal('card', 10, 2);

            $table->decimal('refund', 10, 2)->default(0);
            $table->decimal('expense', 10, 2)->default(0);
            $table->decimal('withdrawal', 10, 2)->default(0);

            $table->decimal('amount_in_box', 10, 2);
            $table->decimal('difference', 10, 2);

            $table->string('note')->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['date', 'shift']);
            $table->index(['user_id', 'date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};
