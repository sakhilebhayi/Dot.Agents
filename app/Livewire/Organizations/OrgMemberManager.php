<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Services\Governance\AuditService;
use App\Services\Organizations\PersonInvitations;
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

    #[Computed]
    public function waiting()
    {
        return app(PersonInvitations::class)->waiting($this->organization);
    }

    public function invite(): void
    {
        $this->validate();

        $org = $this->organization;
        $this->authorize('update', $org);
        $this->assertMayAssign($this->inviteRole);

        if ($org->users()->where('email', $this->inviteEmail)->exists()) {
            $this->addError('inviteEmail', 'This person is already in the business.');

            return;
        }

        $sent = app(PersonInvitations::class)->send($org, $this->inviteEmail, $this->inviteRole, auth()->user());

        session()->flash('member_success', $sent['mailed'] ? "Invitation sent to {$this->inviteEmail}." : null);
        session()->flash('member_link', $sent['mailed'] ? null : $sent['link']);

        unset($this->waiting);
        $this->inviteEmail = '';
        $this->inviteRole = OrgRoles::OPERATOR;
        $this->showInviteForm = false;
    }

    public function cancelInvitation(int $id): void
    {
        $org = $this->organization;
        $this->authorize('update', $org);

        app(PersonInvitations::class)->cancel($org, $id);
        unset($this->waiting);
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
