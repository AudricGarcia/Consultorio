<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PendingVoiceAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PendingVoiceAction>
 */
class PendingVoiceActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'payload' => [
                'tooth_code' => '36',
                'surfaces' => ['occlusal'],
                'finding_type' => 'caries',
                'certainty' => 'observed',
                'treatment_status' => 'none',
                'description' => null,
            ],
            'expires_at' => now()->addMinutes(2),
            'consumed_at' => null,
        ];
    }
}
