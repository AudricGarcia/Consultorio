<?php

namespace Database\Factories;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlanInstallment>
 */
class TreatmentPlanInstallmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'treatment_plan_id' => TreatmentPlan::factory(),
            'installment_number' => 1,
            'due_at' => fake()->dateTimeBetween('today', '+6 months')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 10, 5000),
        ];
    }
}
