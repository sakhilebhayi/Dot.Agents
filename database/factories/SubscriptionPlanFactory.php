<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement(['Starter', 'Professional', 'Enterprise']);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->randomNumber(4),
            'description' => $this->faker->sentence(),
            'billing_cycle' => 'monthly',
            'price' => $this->faker->randomFloat(2, 29, 299),
            'yearly_price' => $this->faker->randomFloat(2, 290, 2990),
            'max_agents' => $this->faker->numberBetween(1, 50),
            'max_users' => $this->faker->numberBetween(5, 100),
            'max_departments' => $this->faker->numberBetween(1, 10),
            'max_workflows' => $this->faker->numberBetween(5, 50),
            'monthly_token_quota' => 1000000,
            'features' => ['feature_a', 'feature_b'],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
