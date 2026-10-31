<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'treatment_plan_id' => null,
            'received_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 1, 1000),
            'currency' => 'PEN',
            'payment_method' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
