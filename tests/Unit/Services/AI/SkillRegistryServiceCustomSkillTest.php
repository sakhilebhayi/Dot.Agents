<?php

namespace Tests\Unit\Services\AI;

use App\Models\AgentSkill;
use App\Models\Organization;
use App\Services\AI\SkillRegistryService;
use App\Skills\WebhookSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillRegistryServiceCustomSkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_a_webhook_skill_for_an_org_defined_custom_skill(): void
    {
        $organization = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($organization->id)->create(['key' => 'custom-inventory-lookup']);

        $resolved = (new SkillRegistryService)->resolve('custom-inventory-lookup');

        $this->assertInstanceOf(WebhookSkill::class, $resolved);
        $this->assertSame($skill->key, $resolved->key());
    }

    public function test_has_implementation_is_true_for_a_webhook_skill(): void
    {
        $organization = Organization::factory()->create();
        AgentSkill::factory()->webhook($organization->id)->create(['key' => 'custom-webhook-skill']);

        $this->assertTrue((new SkillRegistryService)->hasImplementation('custom-webhook-skill'));
    }

    public function test_has_implementation_is_false_for_a_skill_with_neither_class_nor_webhook(): void
    {
        AgentSkill::factory()->create(['key' => 'no-implementation-skill', 'class' => null]);

        $this->assertFalse((new SkillRegistryService)->hasImplementation('no-implementation-skill'));
    }

    public function test_resolve_throws_for_an_inactive_webhook_skill(): void
    {
        $organization = Organization::factory()->create();
        AgentSkill::factory()->webhook($organization->id)->inactive()->create(['key' => 'disabled-custom-skill']);

        $this->expectException(\RuntimeException::class);

        (new SkillRegistryService)->resolve('disabled-custom-skill');
    }
}
