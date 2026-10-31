<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_material_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('treatment_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->text('reason');
            $table->timestamps();
            $table->index(['treatment_session_id', 'created_at']);
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('treatment_material_consumption_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('treatment_material_consumption_id');
        });
        Schema::dropIfExists('treatment_material_consumptions');
    }
};
