<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\SaveBusinessTemplateData;
use App\Models\AgentDeployment;
use App\Models\BusinessTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Keeps an agent's setup as a template for this business. Knowledge is left out: it is chosen again on each agent. */
class SaveBusinessTemplateAction
{
    public function execute(AgentDeployment $agent, SaveBusinessTemplateData $data): BusinessTemplate
    {
        Gate::forUser($data->actor)->authorize('update', $agent);

        $version = $agent->draftVersion()->first() ?? $agent->publishedVersion;

        return BusinessTemplate::create([
            'organization_id' => $agent->organization_id,
            'name' => $data->name,
            'version' => (int) BusinessTemplate::where('organization_id', $agent->organization_id)->where('name', $data->name)->max('version') + 1,
            'role' => $agent->role,
            'responsibility' => $agent->responsibility,
            'summary' => $data->summary,
            'config' => [...$version->settings(), 'knowledge' => []],
            'created_by' => $data->actor->id,
        ]);
    }

    public function remove(User $actor, int $organizationId, string $name): void
    {
        if (! $actor->canInOrganization('agents.manage', $organizationId)) {
            throw new AuthorizationException('You cannot change templates in this business.');
        }

        BusinessTemplate::where('organization_id', $organizationId)->where('name', $name)->delete();
    }
}
