<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Billing\BillingDashboard;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(BillingDashboard::class)
            ->assertOk();
    }

    #[Test]
    public function organization_id_resolves_from_session(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::actingAs($this->user)
            ->test(BillingDashboard::class);

        $this->assertEquals($this->organization->id, $component->get('organizationId'));
    }

    #[Test]
    public function plans_returns_active_plans_only(): void
    {
        $this->actingAs($this->user);

        SubscriptionPlan::factory()->create(['is_active' => true]);
        SubscriptionPlan::factory()->create(['is_active' => false]);

        $plans = Livewire::actingAs($this->user)
            ->test(BillingDashboard::class)
            ->get('plans');

        $this->assertGreaterThanOrEqual(1, $plans->count());
        $plans->each(fn ($p) => $this->assertTrue((bool) $p->is_active));
    }

    #[Test]
    public function current_month_usage_returns_required_keys(): void
    {
        $this->actingAs($this->user);

        $usage = Livewire::actingAs($this->user)
            ->test(BillingDashboard::class)
            ->get('currentMonthUsage');

        $this->assertArrayHasKey('tokens', $usage);
        $this->assertArrayHasKey('tasks', $usage);
        $this->assertArrayHasKey('api_calls', $usage);
        $this->assertArrayHasKey('total_cost', $usage);
    }

    #[Test]
    public function invoices_are_scoped_to_organization(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();

        Invoice::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create(['organization_id' => $otherOrg->id]);

        $invoices = Livewire::actingAs($this->user)
            ->test(BillingDashboard::class)
            ->get('invoices');

        $this->assertCount(1, $invoices);
        $this->assertEquals($this->organization->id, $invoices->first()->organization_id);
    }
}
