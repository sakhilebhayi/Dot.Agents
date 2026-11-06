<?php

namespace Tests\Feature\Services;

use App\Models\AgentDeployment;
use App\Models\AgentToolPermission;
use App\Models\Organization;
use App\Models\User;
use App\Services\AI\ToolPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ToolPermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ToolPermissionService $service;

    private Organization $organization;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ToolPermissionService;
        $user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $user->id]);
        session(['current_organization_id' => $this->organization->id]);
        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
        Cache::flush();
    }

    public function test_tool_is_refused_by_default_when_no_rules_exist(): void
    {
        $permitted = $this->service->isToolPermitted($this->deployment, 'financial_reports');

        $this->assertFalse($permitted);
    }

    public function test_org_level_deny_blocks_tool(): void
    {
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'customer_pii',
            'permission' => 'deny',
            'is_active' => true,
        ]);

        Cache::flush();
        $permitted = $this->service->isToolPermitted($this->deployment, 'customer_pii');

        $this->assertFalse($permitted);
    }

    public function test_deployment_level_deny_overrides_org_allow(): void
    {
        // Org allows the tool
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'analytics',
            'permission' => 'allow',
            'is_active' => true,
        ]);

        // But deployment explicitly denies it
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => $this->deployment->id,
            'tool_name' => 'analytics',
            'permission' => 'deny',
            'is_active' => true,
        ]);

        Cache::flush();
        $permitted = $this->service->isToolPermitted($this->deployment, 'analytics');

        $this->assertFalse($permitted);
    }

    public function test_role_scoped_deny_only_applies_to_matching_role(): void
    {
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'hr_data',
            'permission' => 'deny',
            'role_scope' => 'developer',
            'is_active' => true,
        ]);

        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'hr_data',
            'permission' => 'allow',
            'is_active' => true,
        ]);

        Cache::flush();

        // Developer should be blocked
        $this->assertFalse($this->service->isToolPermitted($this->deployment, 'hr_data', 'developer'));

        // Admin should still have access (rule doesn't apply)
        Cache::flush();
        $this->assertTrue($this->service->isToolPermitted($this->deployment, 'hr_data', 'admin'));
    }

    public function test_assert_tool_permitted_throws_when_denied(): void
    {
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'restricted_tool',
            'permission' => 'deny',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not permitted/');

        Cache::flush();
        $this->service->assertToolPermitted($this->deployment, 'restricted_tool');
    }

    public function test_inactive_rule_is_ignored(): void
    {
        AgentToolPermission::create([
            'organization_id' => $this->organization->id,
            'agent_deployment_id' => null,
            'tool_name' => 'some_tool',
            'permission' => 'allow',
            'is_active' => false, // inactive
        ]);

        Cache::flush();
        $permitted = $this->service->isToolPermitted($this->deployment, 'some_tool');

        $this->assertFalse($permitted);
    }
}
