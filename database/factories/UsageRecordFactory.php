<?php

namespace Database\Factories;

use App\Models\AgentDeployment;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageRecordFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(100, 10000);
        $unitCost = $this->faker->randomFloat(8, 0.0001, 0.001);

        return [
            'organization_id' => Organization::factory(),
            'agent_deployment_id' => AgentDeployment::factory(),
            'metric_type' => $this->faker->randomElement(['tokens', 'tasks', 'api_calls', 'storage_gb']),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($quantity * $unitCost, 4),
            'model_used' => $this->faker->randomElement(['gpt-4o', 'gpt-4o-mini', 'claude-sonnet-5']),
            'recorded_date' => now()->toDateString(),
        ];
    }
}
