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
        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->foreignId('dental_finding_id')->nullable()->after('appointment_id')->constrained('odontogram_findings')->restrictOnDelete();
            $table->char('tooth_code', 2)->nullable()->after('dental_finding_id');
            $table->json('surfaces')->nullable()->after('tooth_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dental_finding_id');
            $table->dropColumn(['tooth_code', 'surfaces']);
        });
    }
};
