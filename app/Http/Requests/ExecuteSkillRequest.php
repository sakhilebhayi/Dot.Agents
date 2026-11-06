<?php

namespace App\Http\Requests;

use App\Support\TenantRules;
use Illuminate\Foundation\Http\FormRequest;

class ExecuteSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via Gate::authorize('view', $deployment) in controller
    }

    public function rules(): array
    {
        return [
            'trigger' => ['required', 'string', 'in:on_demand,pre_task,post_task,scheduled,delegated'],
            'input' => ['nullable', 'array'],
            'task_id' => ['nullable', 'integer', TenantRules::exists('agent_tasks')],
            'justification' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
