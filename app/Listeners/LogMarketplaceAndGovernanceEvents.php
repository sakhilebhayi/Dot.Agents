<?php

namespace App\Listeners;

use App\Events\DecisionLogCreated;
use App\Events\PluginInstalled;
use App\Events\PluginUninstalled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogMarketplaceAndGovernanceEvents implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'governance';

    public int $tries = 3;

    public function handlePluginInstalled(PluginInstalled $event): void
    {
        Log::info('marketplace.plugin_installed', [
            'organization_id' => $event->installation->organization_id,
            'plugin_id' => $event->installation->plugin_id,
            'installation_id' => $event->installation->id,
        ]);
    }

    public function handlePluginUninstalled(PluginUninstalled $event): void
    {
        Log::info('marketplace.plugin_uninstalled', [
            'organization_id' => $event->organizationId,
            'plugin_id' => $event->pluginId,
            'plugin_name' => $event->pluginName,
        ]);
    }

    public function handleDecisionLogCreated(DecisionLogCreated $event): void
    {
        Log::info('governance.decision_log_created', [
            'organization_id' => $event->decisionLog->organization_id,
            'decision_log_id' => $event->decisionLog->id,
            'deployment_id' => $event->decisionLog->agent_deployment_id,
            'delusion_risk_score' => $event->decisionLog->delusion_risk_score,
            'requires_human_review' => $event->decisionLog->requires_human_review,
        ]);
    }
}
