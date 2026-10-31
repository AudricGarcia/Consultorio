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
        Schema::create('odontogram_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_record_id')->nullable()->constrained()->nullOnDelete();
            $table->char('tooth_code', 2);
            $table->json('surfaces')->nullable();
            $table->string('finding_type');
            $table->string('certainty')->default('observed');
            $table->string('treatment_status')->default('none');
            $table->text('description')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['patient_id', 'tooth_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odontogram_findings');
    }
};
