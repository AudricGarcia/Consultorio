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
        Schema::create('dental_finding_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('original_finding_id')->unique()->constrained('odontogram_findings')->restrictOnDelete();
            $table->foreignId('replacement_finding_id')->unique()->constrained('odontogram_findings')->restrictOnDelete();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dental_finding_corrections');
    }
};
