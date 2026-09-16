<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Billing\BillingSettings;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, [
            'role' => 'owner',
            'is_primary' => true,
            'joined_at' => now(),
        ]);
        session(['current_organization_id' => $this->organization->id]);
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(BillingSettings::class)
            ->assertOk();
    }

    #[Test]
    public function it_shows_real_plan_name_price_and_renewal_date_for_a_subscribed_organization(): void
    {
        $this->actingAs($this->user);

        $plan = SubscriptionPlan::factory()->create([
            'name' => 'Growth Squad',
            'price' => 149,
            'is_active' => true,
        ]);

        $subscription = OrganizationSubscription::factory()->create([
            'organization_id' => $this->organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'amount' => 149.00,
            'billing_cycle' => 'monthly',
            'current_period_end' => now()->addDays(20),
        ]);

        $invoice = Invoice::factory()->paid()->create([
            'organization_id' => $this->organization->id,
            'invoice_number' => 'INV-000777',
            'total' => 149.00,
            'invoice_date' => now()->subDays(10),
        ]);

        $component = Livewire::actingAs($this->user)->test(BillingSettings::class);

        $component->assertOk()
            ->assertSee('Growth Squad')
            ->assertSee('149.00')
            ->assertSee($subscription->current_period_end->format('F j, Y'))
            ->assertSee('INV-000777');
    }

    #[Test]
    public function it_scopes_subscription_and_invoices_to_the_current_organization(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $otherPlan = SubscriptionPlan::factory()->create(['name' => 'Rival Plan']);
        OrganizationSubscription::factory()->create([
            'organization_id' => $otherOrg->id,
            'plan_id' => $otherPlan->id,
            'status' => 'active',
        ]);
        Invoice::factory()->create([
            'organization_id' => $otherOrg->id,
            'invoice_number' => 'INV-OTHERORG',
        ]);

        $component = Livewire::actingAs($this->user)->test(BillingSettings::class);

        $component->assertOk();
        $this->assertNull($component->get('subscription'));
        $this->assertCount(0, $component->get('invoices'));

        $html = $component->html();
        $this->assertStringNotContainsString('Rival Plan', $html);
        $this->assertStringNotContainsString('INV-OTHERORG', $html);
    }

    #[Test]
    public function it_shows_an_honest_empty_state_when_the_organization_has_no_subscription(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::actingAs($this->user)->test(BillingSettings::class);

        $component->assertOk();

        $this->assertNull($component->get('subscription'));
        $this->assertCount(0, $component->get('invoices'));

        $html = $component->html();

        $this->assertStringContainsString("doesn't have an active subscription yet", $html);
        $this->assertStringContainsString(route('billing.plans'), $html);
        $this->assertStringContainsString('No invoices yet', $html);

        $this->assertStringNotContainsString('4242', $html);
        $this->assertStringNotContainsString('Pro — $149/mo', $html);
    }

    #[Test]
    public function it_indicates_stripe_connection_without_fabricating_card_details(): void
    {
        $this->actingAs($this->user);

        $this->organization->forceFill(['stripe_customer_id' => 'cus_test123'])->save();

        $component = Livewire::actingAs($this->user)->test(BillingSettings::class);

        $html = $component->html();

        $this->assertStringContainsString('Connected to Stripe', $html);
        $this->assertStringNotContainsString('4242', $html);
        $this->assertStringNotContainsString('Expires', $html);
    }
}
