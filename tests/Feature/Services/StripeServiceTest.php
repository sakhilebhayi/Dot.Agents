<?php

namespace Tests\Feature\Services;

use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $user->id]);
    }

    /**
     * Swaps StripeService's private, constructor-created StripeClient for a fake via
     * reflection, since the client isn't injected and there's no fake HTTP transport
     * wired up for the SDK in this suite. The fake extends the real StripeClient (so
     * the typed property accepts it) and overrides __get('customers') so no network
     * call or real API key is ever needed.
     */
    private function serviceWithFakeStripeClient(object $fakeCustomers): StripeService
    {
        $fakeClient = new class($fakeCustomers) extends StripeClient
        {
            public function __construct(private object $fakeCustomers)
            {
                // Deliberately skip parent::__construct() — it requires a real api key.
            }

            public function __get($name)
            {
                return $name === 'customers' ? $this->fakeCustomers : parent::__get($name);
            }
        };

        // StripeService's constructor builds a real StripeClient(config(...)) before
        // we get a chance to swap it below — testing env has no real secret configured.
        config(['services.stripe.secret' => 'sk_test_fake']);

        $service = new StripeService;
        $property = new \ReflectionProperty(StripeService::class, 'stripe');
        $property->setAccessible(true);
        $property->setValue($service, $fakeClient);

        return $service;
    }

    public function test_ensure_customer_persists_the_stripe_customer_id_on_the_organization(): void
    {
        $fakeCustomers = new class
        {
            public int $createCalls = 0;

            public function create(array $params): object
            {
                $this->createCalls++;

                return (object) ['id' => 'cus_test_persisted'];
            }
        };

        $service = $this->serviceWithFakeStripeClient($fakeCustomers);

        $returnedId = $service->ensureCustomer($this->organization);

        $this->assertSame('cus_test_persisted', $returnedId);
        $this->assertSame(1, $fakeCustomers->createCalls);
        $this->assertDatabaseHas('organizations', [
            'id' => $this->organization->id,
            'stripe_customer_id' => 'cus_test_persisted',
        ]);
    }

    public function test_ensure_customer_does_not_call_stripe_again_once_a_customer_id_exists(): void
    {
        $this->organization->update(['stripe_customer_id' => 'cus_already_set']);

        $fakeCustomers = new class
        {
            public int $createCalls = 0;

            public function create(array $params): object
            {
                $this->createCalls++;

                return (object) ['id' => 'should_not_be_used'];
            }
        };

        $service = $this->serviceWithFakeStripeClient($fakeCustomers);

        $this->assertSame('cus_already_set', $service->ensureCustomer($this->organization));
        $this->assertSame(0, $fakeCustomers->createCalls);
    }
}
