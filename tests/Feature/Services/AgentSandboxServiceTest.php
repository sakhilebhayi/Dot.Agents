<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use App\Services\AI\AgentSandboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AgentSandboxServiceTest extends TestCase
{
    use RefreshDatabase;

    private AgentSandboxService $sandbox;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::factory()->create();

        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->sandbox = app(AgentSandboxService::class);
    }

    public function test_assert_permitted_passes_for_valid_action(): void
    {
        // Should not throw for a standard action
        $this->sandbox->assertPermitted($this->deployment, 'process_message', [
            'organization_id' => $this->deployment->organization_id,
        ]);

        $this->assertTrue(true, 'assertPermitted should not throw for valid action');
    }

    public function test_assert_permitted_throws_on_cross_org_access(): void
    {
        $otherOrg = Organization::factory()->create();

        $this->expectException(RuntimeException::class);

        $this->sandbox->assertPermitted($this->deployment, 'process_message', [
            'organization_id' => $otherOrg->id,
        ]);
    }

    public function test_enforce_token_budget_passes_within_limit(): void
    {
        // Should not throw when within limit
        $this->sandbox->enforceTokenBudget($this->deployment, 1000);
        $this->assertTrue(true);
    }

    public function test_enforce_token_budget_throws_when_exceeded(): void
    {
        $this->expectException(RuntimeException::class);

        // 33000 tokens well exceeds the default 32000 limit
        $this->sandbox->enforceTokenBudget($this->deployment, 33000);
    }

    public function test_enforce_tool_call_limit_passes_within_limit(): void
    {
        // Should not throw when within limit
        $this->sandbox->enforceToolCallLimit($this->deployment, 5);
        $this->assertTrue(true);
    }

    public function test_enforce_tool_call_limit_throws_when_exceeded(): void
    {
        $this->expectException(RuntimeException::class);

        // 16 tool calls exceeds the default 15 limit
        $this->sandbox->enforceToolCallLimit($this->deployment, 16);
    }
}
