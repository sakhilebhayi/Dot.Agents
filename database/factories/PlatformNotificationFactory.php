<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlatformNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['alert', 'approval_request', 'task_complete', 'system', 'billing', 'security']),
            'channel' => 'platform',
            'title' => $this->faker->sentence(5),
            'body' => $this->faker->sentence(10),
            'data' => [],
            'action_url' => null,
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'read_at' => null,
            'acted_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }

    public function critical(): static
    {
        return $this->state(['priority' => 'urgent', 'type' => 'security']);
    }
}
