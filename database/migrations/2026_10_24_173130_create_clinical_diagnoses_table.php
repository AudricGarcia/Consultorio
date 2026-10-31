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
        Schema::create('clinical_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_record_id')->constrained()->restrictOnDelete();
            $table->char('tooth_code', 2)->nullable();
            $table->text('diagnosis');
            $table->string('certainty')->default('confirmed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['clinical_record_id', 'tooth_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_diagnoses');
    }
};
