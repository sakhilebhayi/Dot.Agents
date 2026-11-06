<?php

namespace App\Services\Organizations;

use App\Mail\PersonInvitationEmail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bringing a person into a business by email. Accounts are made at first sign-in, so an invitation has to wait
 * for someone who may not have one yet. It joins only the account with the invited address, and only by the link.
 */
class PersonInvitations
{
    public const DAYS_VALID = 7;

    /**
     * Sends a fresh link. Inviting the same address again replaces the earlier link.
     *
     * @return array{link: string, mailed: bool}
     */
    public function send(Organization $organization, string $email, string $role, User $by): array
    {
        $email = Str::lower(trim($email));
        $token = Str::random(48);

        OrganizationInvitation::query()
            ->where('organization_id', $organization->id)->where('email', $email)->whereNull('accepted_at')->delete();

        OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => self::hash($token),
            'invited_by' => $by->id,
            'expires_at' => now()->addDays(self::DAYS_VALID),
        ]);

        $link = route('invitations.show', $token);

        app(AuditService::class)->logUserAction(
            event: 'member.invited',
            description: 'Invited a person to the business',
            data: ['role' => $role],
            subject: $organization,
        );

        return ['link' => $link, 'mailed' => $this->mail($organization, $email, $role, $link, $by)];
    }

    /** @return Collection<int, OrganizationInvitation> */
    public function waiting(Organization $organization): Collection
    {
        return OrganizationInvitation::query()->open()
            ->where('organization_id', $organization->id)->latest()->get();
    }

    public function cancel(Organization $organization, int $invitationId): void
    {
        OrganizationInvitation::query()
            ->where('organization_id', $organization->id)->whereKey($invitationId)->whereNull('accepted_at')->delete();
    }

    public function find(string $token): ?OrganizationInvitation
    {
        /** @var OrganizationInvitation|null */
        return OrganizationInvitation::withoutGlobalScopes()->open()->with('organization')
            ->where('token_hash', self::hash($token))->first();
    }

    /** Whether this account is the one that was invited. */
    public function isFor(OrganizationInvitation $invitation, User $user): bool
    {
        return Str::lower($user->email) === $invitation->email;
    }

    public function accept(OrganizationInvitation $invitation, User $user): void
    {
        abort_unless($this->isFor($invitation, $user), 403, 'This invitation is for another email address.');

        /** @var Organization $organization */
        $organization = $invitation->organization;

        if (! $organization->users()->where('users.id', $user->id)->exists()) {
            $organization->users()->attach($user->id, [
                'role' => $invitation->role,
                'is_primary' => $user->organizations()->count() === 0,
                'joined_at' => now(),
            ]);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();
    }

    private function mail(Organization $organization, string $email, string $role, string $link, User $by): bool
    {
        try {
            Mail::to($email)->send(new PersonInvitationEmail($organization->name, $by->name, $role, $link));

            return true;
        } catch (Throwable $e) {
            Log::warning('An invitation email could not be sent.', ['organization' => $organization->id, 'error' => $e::class]);

            return false;
        }
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
