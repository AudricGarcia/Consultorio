<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use App\Models\ClinicalRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalRecord>
 */
class ClinicalRecordFactory extends Factory
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
            'encountered_at' => now(),
            'record_type' => 'encounter',
            'status' => 'draft',
        ];
    }
}
