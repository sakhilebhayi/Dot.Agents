<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgentSkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'skill.'.Str::slug($this->faker->words(3, true)),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'layer' => $this->faker->randomElement(['core', 'domain', 'extended']),
            'category' => $this->faker->randomElement(['analysis', 'reporting', 'automation', 'communication']),
            'department' => $this->faker->randomElement(['finance', 'hr', 'it', 'sales']),
            'agent_type' => $this->faker->randomElement(['finance_controller', 'hr_manager', 'it_operations']),
            'output_type' => $this->faker->randomElement(['report', 'action', 'notification', 'data']),
            'risk_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'approval_required' => false,
            'audit_required' => true,
            'delegation_capable' => false,
            'is_active' => true,
            'confidence_score' => $this->faker->numberBetween(60, 95),
        ];
    }

    public function requiresApproval(): static
    {
        return $this->state(['approval_required' => true, 'risk_level' => 'high']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** An org-defined custom skill executed via webhook rather than a PHP class. */
    public function webhook(int $organizationId): static
    {
        return $this->state([
            'organization_id' => $organizationId,
            'layer' => 'workforce',
            'category' => 'custom',
            'class' => null,
            'webhook_url' => 'https://example.test/skills/'.$this->faker->slug(2),
            'webhook_timeout_seconds' => 15,
            'is_built_in' => false,
        ]);
    }
}
