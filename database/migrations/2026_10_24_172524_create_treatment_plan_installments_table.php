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
        Schema::create('treatment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_at');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['treatment_plan_id', 'installment_number']);
            $table->index(['due_at', 'treatment_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_plan_installments');
    }
};
