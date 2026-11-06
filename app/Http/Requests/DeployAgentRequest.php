<?php

namespace App\Http\Requests;

use App\DTOs\Agents\DeployAgentData;
use App\Models\AgentDeployment;
use App\Services\Governance\AuditService;
use App\Support\TenantContext;
use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class DeployAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tokenCan('deploy-agents')
            || $this->user()?->can('create', [AgentDeployment::class, (int) TenantContext::id()]);
    }

    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'name' => ['required', 'string', 'max:100'],
            'deployment_mode' => ['required', 'in:advisory,semi-autonomous,autonomous,executive_approval'],
            'confidence_threshold' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'department_id' => ['nullable', 'integer', TenantRules::exists('departments')],
            'custom_instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $instructions = $this->input('custom_instructions');
            if ($instructions && app(AuditService::class)->detectPromptInjection($instructions)) {
                $validator->errors()->add('custom_instructions', 'Custom instructions contain disallowed content.');
            }
        });
    }

    public function toDeployData(): DeployAgentData
    {
        return DeployAgentData::fromArray([
            ...$this->validated(),
            'organization_id' => session('current_organization_id'),
            'deployed_by' => $this->user()->id,
        ]);
    }
}
