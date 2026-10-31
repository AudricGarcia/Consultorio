<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlan>
 */
class TreatmentPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'professional_id' => User::factory(),
            'title' => fake()->words(3, true),
            'status' => 'accepted',
            'accepted_at' => now(),
        ];
    }
}
