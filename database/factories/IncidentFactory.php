<?php

namespace Database\Factories;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $startedAt = now()->subHour();

        return [
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'status' => Incident::STATUS_RESOLVED,
            'affected_service' => 'Cloud VPS',
            'impact_summary' => fake()->sentence(),
            'summary' => fake()->paragraph(),
            'root_cause' => fake()->paragraph(),
            'customer_impact' => fake()->paragraph(),
            'resolution' => fake()->paragraph(),
            'next_steps' => fake()->paragraph(),
            'final_status' => 'Resolved',
            'started_at' => $startedAt,
            'ended_at' => now(),
            'is_published' => true,
            'published_at' => now(),
            'meta_description' => fake()->sentence(10),
        ];
    }
}
