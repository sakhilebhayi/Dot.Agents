<?php

namespace Tests\Feature\Actions\Marketplace;

use App\Actions\Marketplace\InstallAgentPluginAction;
use App\Events\PluginInstalled;
use App\Events\PluginUninstalled;
use App\Models\AgentPlugin;
use App\Models\AgentPluginInstallation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallAgentPluginActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->actingAs($this->user);
        Gate::before(fn () => true);
        session(['current_organization_id' => $this->organization->id]);

        $this->plugin = AgentPlugin::withoutGlobalScopes()->create([
            'key' => 'test-plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'description' => 'A test plugin',
            'class' => 'TestPlugin',
            'manifest' => [],
            'category' => 'utility',
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    #[Test]
    public function it_installs_a_plugin_and_fires_plugin_installed_event(): void
    {
        Event::fake([PluginInstalled::class]);

        $action = app(InstallAgentPluginAction::class);
        $installation = $action->execute($this->plugin, $this->organization);

        $this->assertInstanceOf(AgentPluginInstallation::class, $installation);
        $this->assertEquals($this->plugin->id, $installation->plugin_id);
        $this->assertEquals($this->organization->id, $installation->organization_id);

        Event::assertDispatched(PluginInstalled::class, function (PluginInstalled $event) use ($installation) {
            return $event->installation->id === $installation->id;
        });
    }

    #[Test]
    public function it_is_idempotent_and_does_not_duplicate_install(): void
    {
        Event::fake([PluginInstalled::class]);

        $action = app(InstallAgentPluginAction::class);
        $first = $action->execute($this->plugin, $this->organization);
        $second = $action->execute($this->plugin, $this->organization);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('agent_plugin_installations', 1);

        // Second call is idempotent — event should only fire once
        Event::assertDispatchedTimes(PluginInstalled::class, 1);
    }

    #[Test]
    public function it_uninstalls_a_plugin_and_fires_plugin_uninstalled_event(): void
    {
        Event::fake([PluginUninstalled::class]);

        $installation = AgentPluginInstallation::create([
            'plugin_id' => $this->plugin->id,
            'organization_id' => $this->organization->id,
            'installed_by' => $this->user->id,
            'config' => [],
            'installed_at' => now(),
        ]);

        $action = app(InstallAgentPluginAction::class);
        $action->uninstall($installation);

        $this->assertDatabaseMissing('agent_plugin_installations', ['id' => $installation->id]);

        Event::assertDispatched(PluginUninstalled::class, function (PluginUninstalled $event) use ($installation) {
            return $event->organizationId === $installation->organization_id
                && $event->pluginId === $installation->plugin_id;
        });
    }
}
