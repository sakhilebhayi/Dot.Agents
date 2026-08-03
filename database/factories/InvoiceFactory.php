<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 500);
        $tax = round($subtotal * 0.1, 2);

        return [
            'uuid' => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'stripe_invoice_id' => 'in_'.fake()->unique()->bothify('??########'),
            'status' => fake()->randomElement(['draft', 'open', 'paid', 'uncollectible', 'void']),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
            'currency' => 'USD',
            'line_items' => [
                ['description' => fake()->sentence(3), 'amount' => $subtotal],
            ],
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'paid_at' => null,
            'payment_method' => null,
            'pdf_url' => null,
            'billing_details' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => 'card',
        ]);
    }
}
