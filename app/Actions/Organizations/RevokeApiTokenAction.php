<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Events\ApiTokenRevoked;
use App\Services\Governance\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeApiTokenAction
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Revoke a Sanctum API token.
     * Authorizes that the authenticated user owns the token's parent model.
     *
     * @param  PersonalAccessToken  $token
     */
    public function execute(Model $token): void
    {
        Gate::authorize('update', $token->tokenable);

        $tokenName = $token->name;
        $token->delete();

        $this->auditService->logUserAction(
            event: 'api_key.revoked',
            description: "API key revoked: {$tokenName}",
        );

        event(new ApiTokenRevoked($token->tokenable_id, $tokenName));
    }
}
