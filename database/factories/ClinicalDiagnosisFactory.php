<?php

namespace Database\Factories;

use App\Models\ClinicalDiagnosis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalDiagnosis>
 */
class ClinicalDiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinical_record_id' => \App\Models\ClinicalRecord::factory(),
            'tooth_code' => fake()->randomElement(['11', '16', '21', '26', '31', '36', '41', '46']),
            'diagnosis' => fake()->randomElement(['Caries dental', 'Gingivitis', 'Pulpitis']),
            'certainty' => 'confirmed',
            'notes' => null,
        ];
    }
}
