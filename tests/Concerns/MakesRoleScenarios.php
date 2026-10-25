<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;

/**
 * People in one company: a team owner, plus members holding a given Jetstream team
 * role and a given organization role. Organization role `owner` is what lets the
 * app's policies say yes, so a viewer that is still refused proves the read-only
 * rule (and not a missing org permission) is what stopped them.
 */
trait MakesRoleScenarios
{
    protected User $companyOwner;

    protected Team $company;

    protected Organization $organization;

    protected function setUpCompany(): void
    {
        $this->companyOwner = User::factory()->withPersonalTeam()->create($this->consented());
        $this->company = $this->companyOwner->ownedTeams()->firstOrFail();
        $this->companyOwner->forceFill(['current_team_id' => $this->company->id])->save();

        $this->organization = Organization::factory()->create(['owner_id' => $this->companyOwner->id]);
        $this->organization->users()->attach($this->companyOwner->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
    }

    /** A member of the company with the given team role (and org role), working in the company. */
    protected function memberWith(string $teamRole, string $orgRole = 'owner'): User
    {
        $user = User::factory()->create($this->consented());

        $this->company->users()->attach($user->id, ['role' => $teamRole]);
        $user->forceFill(['current_team_id' => $this->company->id])->save();
        $this->organization->users()->attach($user->id, ['role' => $orgRole, 'is_primary' => true, 'joined_at' => now()]);

        return $user->fresh();
    }

    protected function actingInCompany(User $user): static
    {
        session(['current_organization_id' => $this->organization->id]);

        return $this->actingAs($user);
    }

    /** @return array<string, mixed> */
    protected function consented(): array
    {
        return ['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]];
    }
}
