<?php

namespace App\Actions\Marketplace;

use App\Events\PluginInstalled;
use App\Events\PluginUninstalled;
use App\Models\AgentPlugin;
use App\Models\AgentPluginInstallation;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class InstallAgentPluginAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Install a platform plugin into an organisation.
     *
     * Idempotent: re-installing an already-installed plugin returns the
     * existing installation record rather than creating a duplicate.
     */
    public function execute(
        AgentPlugin $plugin,
        Organization $organization,
        array $config = [],
    ): AgentPluginInstallation {
        Gate::authorize('create', [AgentPluginInstallation::class, $organization]);

        // Idempotency — prevent duplicate installs
        $existing = AgentPluginInstallation::where('plugin_id', $plugin->id)
            ->where('organization_id', $organization->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $installation = AgentPluginInstallation::create([
            'plugin_id' => $plugin->id,
            'organization_id' => $organization->id,
            'installed_by' => auth()->id(),
            'config' => $config,
            'installed_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'marketplace.plugin_installed',
            description: "Plugin '{$plugin->name}' (v{$plugin->version}) installed for organization '{$organization->name}'",
            data: [
                'plugin_id' => $plugin->id,
                'plugin_key' => $plugin->key,
                'plugin_version' => $plugin->version,
            ],
            subject: $installation,
        );

        event(new PluginInstalled($installation));

        return $installation;
    }

    /**
     * Uninstall a plugin from an organisation.
     */
    public function uninstall(AgentPluginInstallation $installation): void
    {
        Gate::authorize('delete', $installation);

        $pluginName = $installation->plugin?->name ?? "Plugin #{$installation->plugin_id}";
        $orgId = $installation->organization_id;
        $pluginId = $installation->plugin_id;

        $this->auditService->logUserAction(
            event: 'marketplace.plugin_uninstalled',
            description: "Plugin '{$pluginName}' uninstalled from organization #{$installation->organization_id}",
            data: ['plugin_id' => $installation->plugin_id],
            subject: $installation,
        );

        $installation->delete();

        event(new PluginUninstalled($orgId, $pluginId, $pluginName));
    }
}
