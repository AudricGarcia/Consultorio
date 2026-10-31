<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_plan_installment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'treatment_plan_installment_id']);
            $table->index(['treatment_plan_installment_id', 'payment_id']);
        });

        DB::table('treatment_plans')->whereNotNull('accepted_at')->whereIn('status', ['accepted', 'in_progress', 'completed'])
            ->orderBy('id')->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $totalCents = (int) round((float) DB::table('treatment_plan_items')->where('treatment_plan_id', $plan->id)
                        ->sum(DB::raw('quantity * unit_price')) * 100);
                    if ($totalCents < 1) {
                        continue;
                    }

                    $installmentId = DB::table('treatment_plan_installments')->insertGetId([
                        'treatment_plan_id' => $plan->id,
                        'installment_number' => 1,
                        'due_at' => substr($plan->accepted_at, 0, 10),
                        'amount' => number_format($totalCents / 100, 2, '.', ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $remainingCents = $totalCents;
                    $payments = DB::table('payments')->where('treatment_plan_id', $plan->id)->where('status', 'paid')->orderBy('id')->get(['id', 'amount']);
                    foreach ($payments as $payment) {
                        $allocatedCents = min($remainingCents, (int) round((float) $payment->amount * 100));
                        if ($allocatedCents < 1) {
                            continue;
                        }

                        DB::table('payment_allocations')->insert([
                            'payment_id' => $payment->id,
                            'treatment_plan_installment_id' => $installmentId,
                            'amount' => number_format($allocatedCents / 100, 2, '.', ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $remainingCents -= $allocatedCents;
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
