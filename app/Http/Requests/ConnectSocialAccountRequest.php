<?php

namespace App\Http\Requests;

use App\Models\SocialAccount;
use Illuminate\Foundation\Http\FormRequest;

class ConnectSocialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // SocialAccountPolicy::create() takes a non-nullable int organization
        // id. OrganizationContextMiddleware can leave the session org null
        // for this request (membership revoked mid-request), so passing it
        // straight through would TypeError instead of denying access.
        $orgId = session('current_organization_id');
        if (! $orgId) {
            return false;
        }

        return (bool) $this->user()?->can('create', [SocialAccount::class, (int) $orgId]);
    }

    public function rules(): array
    {
        return [
            'agent_deployment_id' => ['nullable', 'integer', 'exists:agent_deployments,id'],
        ];
    }
}
