<?php

namespace App\Actions\Skills;

use App\DTOs\Skills\CreateCustomSkillData;
use App\Models\AgentSkill;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateCustomSkillAction
{
    /**
     * Create an organization's own webhook-backed custom skill. It joins
     * the shared agent_skills catalog scoped to this org (organization_id
     * set), invisible to every other org, and can be assigned to any of
     * this org's deployments exactly like a platform-built-in skill --
     * see WebhookSkill and SkillRegistryService::resolve().
     */
    public function execute(CreateCustomSkillData $data): AgentSkill
    {
        Gate::authorize('create', [AgentSkill::class, $data->organizationId]);

        return AgentSkill::create([
            'organization_id' => $data->organizationId,
            'key' => $this->uniqueKey($data->name, $data->organizationId),
            'name' => $data->name,
            'description' => $data->description,
            'layer' => 'workforce',
            'category' => 'custom',
            'webhook_url' => $data->webhookUrl,
            'webhook_headers' => $data->webhookHeaders ?: null,
            'webhook_timeout_seconds' => $data->webhookTimeoutSeconds,
            'output_type' => AgentSkill::OUTPUT_DATA,
            'risk_level' => AgentSkill::RISK_MEDIUM,
            'is_active' => true,
            'is_built_in' => false,
            'requires_ai' => false,
            'approval_required' => false,
            'audit_required' => true,
            'delegation_capable' => false,
        ]);
    }

    /**
     * `key` is unique across the whole shared table, not just per-org, so a
     * plain slug of the name would collide the moment two organizations
     * pick the same skill name.
     */
    private function uniqueKey(string $name, int $organizationId): string
    {
        $base = Str::slug($name).'-org'.$organizationId;
        $key = $base;
        $suffix = 1;

        while (AgentSkill::where('key', $key)->exists()) {
            $key = $base.'-'.(++$suffix);
        }

        return $key;
    }
}
