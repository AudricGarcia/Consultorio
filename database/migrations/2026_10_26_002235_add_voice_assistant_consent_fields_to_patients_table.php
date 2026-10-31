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
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('voice_assistant_consent')->default(false)->after('reminder_consent_at');
            $table->timestamp('voice_assistant_consent_at')->nullable()->after('voice_assistant_consent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['voice_assistant_consent', 'voice_assistant_consent_at']);
        });
    }
};
