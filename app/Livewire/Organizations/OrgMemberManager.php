<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Services\Governance\AuditService;
use App\Support\OrgRoles;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class OrgMemberManager extends Component
{
    #[Validate('required|email|max:255')]
    public string $inviteEmail = '';

    public string $inviteRole = OrgRoles::OPERATOR;

    public bool $showInviteForm = false;

    public ?int $removingUserId = null;

    #[Computed]
    public function organization(): Organization
    {
        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403);

        return Organization::findOrFail($orgId);
    }

    #[Computed]
    public function members()
    {
        return $this->organization
            ->users()
            ->withPivot(['role', 'job_title', 'is_primary', 'joined_at'])
            ->orderByPivot('joined_at')
            ->get();
    }

    public function invite(): void
    {
        $this->validate();

        $org = $this->organization;
        $this->authorize('update', $org);
        $this->assertMayAssign($this->inviteRole);

        $user = User::where('email', $this->inviteEmail)->first();

        if (! $user) {
            $this->addError('inviteEmail', 'No account found with that email address.');

            return;
        }

        if ($org->users()->where('user_id', $user->id)->exists()) {
            $this->addError('inviteEmail', 'This user is already a member.');

            return;
        }

        $org->users()->attach($user->id, [
            'role' => $this->inviteRole,
            'is_primary' => false,
            'joined_at' => now(),
        ]);

        unset($this->members);
        $this->inviteEmail = '';
        $this->inviteRole = OrgRoles::OPERATOR;
        $this->showInviteForm = false;
        session()->flash('member_success', 'Member added successfully.');
    }

    public function confirmRemove(int $userId): void
    {
        $this->removingUserId = $userId;
    }

    public function removeMember(): void
    {
        $org = $this->organization;
        $this->authorize('update', $org);

        if ($this->removingUserId === auth()->id()) {
            session()->flash('member_error', 'You cannot remove yourself.');
            $this->removingUserId = null;

            return;
        }

        if ($org->owner_id === $this->removingUserId) {
            session()->flash('member_error', 'The organization owner cannot be removed.');
            $this->removingUserId = null;

            return;
        }

        $org->users()->detach($this->removingUserId);
        $this->removingUserId = null;
        unset($this->members);
        session()->flash('member_success', 'Member removed.');
    }

    public function updateRole(int $userId, string $role): void
    {
        $org = $this->organization;
        $this->authorize('update', $org);

        $this->assertMayAssign($role);

        $current = $org->users()->where('users.id', $userId)->firstOrFail()->pivot->role;

        abort_if($current === OrgRoles::OWNER && ! $this->actorIsOwner(), 403, 'Only an owner can change an owner.');
        abort_if($userId === (int) $org->owner_id, 422, 'The business owner stays an owner.');

        $org->users()->updateExistingPivot($userId, ['role' => $role]);

        app(AuditService::class)->logUserAction(
            event: 'member.role_changed',
            description: 'Changed a member\'s role',
            data: ['user_id' => $userId, 'from' => $current, 'to' => $role],
            subject: $org,
        );

        unset($this->members);
    }

    /** Roles a person may hand out: any role they could hold, and only an owner makes another owner. */
    private function assertMayAssign(string $role): void
    {
        abort_unless(in_array($role, OrgRoles::ALL, true), 422, 'That is not a role.');
        abort_if($role === OrgRoles::OWNER && ! $this->actorIsOwner(), 403, 'Only an owner can make another owner.');
    }

    private function actorIsOwner(): bool
    {
        return auth()->user()->roleInOrganization($this->organization->id) === OrgRoles::OWNER;
    }

    public function render()
    {
        return view('livewire.organizations.org-member-manager');
    }
}
