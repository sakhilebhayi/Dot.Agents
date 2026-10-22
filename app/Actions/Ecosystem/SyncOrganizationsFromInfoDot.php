<?php

namespace App\Actions\Ecosystem;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Gives every InfoDot company one organization here and keeps the person's
 * role in it in step with InfoDot, on every sign-in.
 *
 * Every data policy in this app checks organization membership, while
 * SyncTeamsFromInfoDot only handles Jetstream teams, so without this a company
 * would have no data of its own and its viewers would be read-only nowhere that
 * matters. It is called by SyncTeamsFromInfoDot with the same set of companies.
 *
 * - Only organizations that carry an infodot_team_uuid are ever created, changed
 *   or left. A personal organization, or one someone was added to by hand, is
 *   never touched.
 * - As with teams, only people InfoDot lists as owner or admin create the company
 *   organization; an ordinary member is attached once it exists.
 * - Role follows InfoDot and is rewritten at every sign-in (owner -> owner,
 *   admin -> admin, editor -> member, viewer and anything unrecognised -> the
 *   read-only viewer role, so an unknown role never gains write access).
 * - A company InfoDot no longer lists for the person is detached from them.
 * - When the sign-in's current company has an organization the person belongs
 *   to, it becomes the session's current organization (a viewer lands read-only).
 */
class SyncOrganizationsFromInfoDot
{
    /**
     * @param  Collection<int|string, mixed>  $listed  non-personal companies from the redeem response, keyed by uuid
     */
    public function __invoke(User $user, Collection $listed, ?string $currentUuid): void
    {
        DB::transaction(function () use ($user, $listed, $currentUuid) {
            foreach ($listed as $uuid => $info) {
                if (is_array($info)) {
                    $this->apply($user, (string) $uuid, $info);
                }
            }

            $this->revoke($user, $listed->keys()->map(fn ($k) => (string) $k)->all());
            $this->selectCurrent($user, $currentUuid);
        });
    }

    /**
     * @param  array<string, mixed>  $info
     */
    private function apply(User $user, string $uuid, array $info): void
    {
        $role = (string) ($info['role'] ?? '');
        $organization = Organization::where('infodot_team_uuid', $uuid)->first();

        if (! $organization) {
            if (! in_array($role, ['owner', 'admin'], true)) {
                return;
            }

            $organization = $this->create($user, $uuid, (string) ($info['name'] ?? 'Company'));
        } elseif (filled($info['name'] ?? null) && $organization->name !== $info['name']) {
            $organization->forceFill(['name' => Str::limit((string) $info['name'], 255, '')])->save();
        }

        $localRole = $this->localRole($role);

        if ($organization->users()->where('users.id', $user->id)->exists()) {
            $organization->users()->updateExistingPivot($user->id, ['role' => $localRole]);
        } else {
            $organization->users()->attach($user->id, ['role' => $localRole, 'is_primary' => false, 'joined_at' => now()]);
        }
    }

    private function create(User $creator, string $uuid, string $name): Organization
    {
        $name = Str::limit(trim($name) !== '' ? trim($name) : 'Company', 255, '');
        $base = Str::slug($name) ?: 'company';
        $slug = $base;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        // Like CreateOrganizationAction, but without OrganizationCreated: that event
        // sends a welcome email to the creator, and a company is not their sign-up.
        return Organization::forceCreate([
            'name' => $name,
            'slug' => $slug,
            'owner_id' => $creator->id,
            'plan' => 'starter',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'trial_ends_at' => now()->addDays(14),
            'infodot_team_uuid' => $uuid,
        ]);
    }

    /**
     * @param  list<string>  $keep
     */
    private function revoke(User $user, array $keep): void
    {
        $stale = $user->organizations()
            ->whereNotNull('organizations.infodot_team_uuid')
            ->whereNotIn('organizations.infodot_team_uuid', $keep)
            ->get();

        foreach ($stale as $organization) {
            if ($organization->owner_id === $user->id) {
                // Access follows membership, not the owner_id column, so they lose it all the same.
                Log::notice('InfoDot no longer lists a company whose organization this person created here.', ['organization_id' => $organization->id, 'user_id' => $user->id]);
            }

            $organization->users()->detach($user->id);

            if ((int) session('current_organization_id') === $organization->id) {
                session()->forget('current_organization_id');
            }
        }
    }

    private function selectCurrent(User $user, ?string $currentUuid): void
    {
        if (! filled($currentUuid)) {
            return;
        }

        $organization = $user->organizations()->where('organizations.infodot_team_uuid', $currentUuid)->first();

        if ($organization) {
            session(['current_organization_id' => $organization->id]);
        }
    }

    /** Least privilege: only owner, admin and editor are recognised; everything else is read-only. */
    public function localRole(string $infodotRole): string
    {
        return match ($infodotRole) {
            'owner' => 'owner',
            'admin' => 'admin',
            'editor' => 'member',
            default => 'viewer',
        };
    }
}
