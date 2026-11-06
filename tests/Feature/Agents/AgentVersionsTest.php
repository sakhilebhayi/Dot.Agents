<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\CreateAgentAction;
use App\Actions\Agents\DeployAgentAction;
use App\Actions\Agents\DuplicateAgentAction;
use App\Actions\Agents\PublishAgentVersionAction;
use App\Actions\Agents\RestoreAgentVersionAction;
use App\Actions\Agents\SaveAgentDraftAction;
use App\DTOs\Agents\CreateAgentData;
use App\DTOs\Agents\DeployAgentData;
use App\DTOs\Agents\PublishAgentVersionData;
use App\DTOs\Agents\SaveAgentDraftData;
use App\Models\Agent;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Agents\AgentConfig;
use App\Services\Agents\AgentPackage;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AgentVersionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->create();
        $this->organization->users()->attach($this->manager->id, ['role' => 'agent_manager', 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->organization->id]);
        $this->actingAs($this->manager);
    }

    private function agent(array $config = ['instructions' => 'Check invoices.']): AgentDeployment
    {
        return app(CreateAgentAction::class)->execute(new CreateAgentData(
            organizationId: $this->organization->id, createdBy: $this->manager->id,
            name: 'Invoice checker', role: 'Accounts clerk', responsibility: 'Check supplier invoices against our terms.', config: $config,
        ));
    }

    private function publish(AgentDeployment $agent): AgentDeployment
    {
        return app(PublishAgentVersionAction::class)->execute($agent->draftVersion()->first(), new PublishAgentVersionData($this->manager->id));
    }

    private function saveDraft(AgentDeployment $agent, string $instructions): AgentConfigVersion
    {
        return app(SaveAgentDraftAction::class)->execute($agent, new SaveAgentDraftData($this->manager->id, ['instructions' => $instructions]));
    }

    public function test_a_new_agent_is_a_private_draft_owned_by_the_business(): void
    {
        $agent = $this->agent();

        $this->assertSame('draft', $agent->status);
        $this->assertSame($this->organization->id, $agent->agent->organization_id);
        $this->assertSame($this->manager->id, $agent->owner_id);
        $this->assertNull($agent->published_version_id);
        $this->assertSame('Check invoices.', $agent->draftVersion->settings()['instructions']);
    }

    public function test_a_private_agent_stays_out_of_another_businesss_catalogue(): void
    {
        $private = $this->agent()->agent;
        $official = Agent::factory()->create();
        TenantContext::lenient(false);

        $other = Organization::factory()->create();

        $this->assertNull(TenantContext::forOrganization($other->id, fn () => Agent::find($private->id)));
        $this->assertNotNull(TenantContext::forOrganization($other->id, fn () => Agent::find($official->id)));
    }

    public function test_publishing_makes_a_draft_the_one_that_runs(): void
    {
        $agent = $this->publish($this->agent());

        $this->assertSame('active', $agent->status);
        $this->assertSame(1, $agent->publishedVersion->number);
        $this->assertNotNull($agent->deployed_at);
    }

    public function test_changing_a_live_agent_leaves_its_published_version_alone_until_published(): void
    {
        $agent = $this->publish($this->agent());

        $draft = $this->saveDraft($agent, 'Check invoices and statements.');

        $this->assertSame(2, $draft->number);
        $this->assertSame('Check invoices.', $agent->fresh()->publishedVersion->settings()['instructions']);

        $this->saveDraft($agent, 'Second thoughts.');
        $this->assertSame(2, $agent->versions()->count(), 'one open draft');

        $agent = $this->publish($agent);
        $this->assertSame('Second thoughts.', $agent->publishedVersion->settings()['instructions']);
        $this->assertSame(AgentConfigVersion::RETIRED, $agent->versions()->where('number', 1)->value('state'));
    }

    public function test_a_published_version_cannot_be_changed(): void
    {
        $version = $this->publish($this->agent())->publishedVersion;

        $this->expectException(LogicException::class);

        $version->update(['config' => ['instructions' => 'Tampered']]);
    }

    public function test_only_a_draft_can_be_published(): void
    {
        $version = $this->publish($this->agent())->publishedVersion;

        $this->expectException(InvalidArgumentException::class);

        app(PublishAgentVersionAction::class)->execute($version, new PublishAgentVersionData($this->manager->id));
    }

    public function test_restoring_brings_an_old_setup_back_as_a_new_version(): void
    {
        $agent = $this->publish($this->agent());
        $first = $agent->publishedVersion;
        $this->saveDraft($agent, 'A change that went badly.');
        $agent = $this->publish($agent);

        $agent = app(RestoreAgentVersionAction::class)->execute($first, new PublishAgentVersionData($this->manager->id));

        $this->assertSame(3, $agent->publishedVersion->number);
        $this->assertSame('Check invoices.', $agent->publishedVersion->settings()['instructions']);
        $this->assertSame('Restored from version 1', $agent->publishedVersion->note);
    }

    public function test_the_setup_is_encrypted_where_it_is_stored(): void
    {
        $version = $this->agent(['instructions' => 'Confidential process'])->draftVersion;

        $this->assertStringNotContainsString('Confidential', (string) DB::table('agent_config_versions')->where('id', $version->id)->value('config'));
    }

    public function test_a_stored_config_is_read_with_defaults_and_without_strangers(): void
    {
        $clean = AgentConfig::normalise(['limits' => ['steps' => 5, 'danger' => 1], 'nonsense' => true]);

        $this->assertSame(5, $clean['limits']['steps']);
        $this->assertSame(20, $clean['limits']['tool_calls']);
        $this->assertArrayNotHasKey('danger', $clean['limits']);
        $this->assertArrayNotHasKey('nonsense', $clean);
    }

    public function test_limits_outside_their_range_are_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveAgentDraftAction::class)->execute($this->agent(), new SaveAgentDraftData($this->manager->id, ['limits' => ['steps' => 100000]]));
    }

    public function test_someone_who_cannot_configure_agents_cannot_change_one(): void
    {
        $agent = $this->agent();
        $operator = User::factory()->create();
        $this->organization->users()->attach($operator->id, ['role' => 'operator', 'joined_at' => now()]);
        $this->actingAs($operator);

        $this->expectException(AuthorizationException::class);

        $this->saveDraft($agent, 'Sneaky');
    }

    public function test_the_owner_of_an_agent_has_to_belong_to_the_business(): void
    {
        $stranger = User::factory()->create();

        $this->expectException(HttpException::class);

        app(SaveAgentDraftAction::class)->execute($this->agent(), new SaveAgentDraftData($this->manager->id, [], ownerId: $stranger->id));
    }

    public function test_a_duplicate_is_a_draft_on_the_same_setup(): void
    {
        $source = $this->publish($this->agent());

        $copy = app(DuplicateAgentAction::class)->execute($source, $this->manager->id);

        $this->assertSame('draft', $copy->status);
        $this->assertSame('Invoice checker copy', $copy->name);
        $this->assertSame('Check invoices.', $copy->draftVersion->settings()['instructions']);
    }

    public function test_an_export_can_be_imported_and_carries_no_knowledge_grants(): void
    {
        $source = $this->agent(['instructions' => 'Check.', 'knowledge' => [4, 5]]);

        $read = AgentPackage::read(json_decode((string) json_encode(AgentPackage::export($source)), true));

        $this->assertSame('Invoice checker', $read['name']);
        $this->assertSame([], $read['config']['knowledge']);
        $this->assertSame('Check.', $read['config']['instructions']);
    }

    public function test_a_file_that_is_not_an_export_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AgentPackage::read(['format' => 'something-else', 'name' => 'X']);
    }

    public function test_a_template_deployment_goes_live_on_its_own_setup(): void
    {
        $agent = Agent::factory()->create(['status' => 'active', 'template' => ['tone' => 'formal']]);
        session(['current_organization_id' => $this->organization->id]);
        $owner = User::factory()->create();
        $this->organization->users()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $this->actingAs($owner);

        $deployment = app(DeployAgentAction::class)->execute(new DeployAgentData(
            agentId: $agent->id, organizationId: $this->organization->id, deployedBy: $owner->id, name: 'Clerk', deploymentMode: 'advisory', customInstructions: 'Be careful.',
        ));

        $version = $deployment->fresh()->publishedVersion;
        $this->assertSame('formal', $version->settings()['tone']);
        $this->assertSame('Be careful.', $version->settings()['instructions']);
    }
}
