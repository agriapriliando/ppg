<?php

namespace Database\Factories;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdmissionPeriod> */
class AdmissionPeriodFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PMB-####-??'),
            'name' => 'Periode Uji PPG',
            'academic_year' => '2026/2027',
            'wave' => 'I',
            'state' => PeriodState::Draft,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['state' => PeriodState::Published, 'is_public' => true]);
    }
}
