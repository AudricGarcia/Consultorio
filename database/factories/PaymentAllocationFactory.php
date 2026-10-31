<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'treatment_plan_installment_id' => TreatmentPlanInstallment::factory(),
            'amount' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
