<?php

namespace App\Support;

use App\Models\PlatformNotification;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Jetstream;
use Livewire\Component;

/**
 * The single definition of "read-only" in Dot.Agents.
 *
 * A person is read-only when they hold the `viewer` role in the team they are
 * currently working in (the Jetstream role InfoDot's viewer is mapped to), or
 * the `viewer` role in the organization the session is working in (the role
 * the members screen already offered). Three layers enforce it, all asking this
 * class, so they cannot drift apart:
 *
 *  - Gate::before   every policy/gate check except the read abilities (this is
 *                   what the Actions and the API controllers authorize through)
 *  - writable       route middleware: no non-GET request, and a few GET routes
 *                   that change state, for a read-only person
 *  - Livewire hook  default-deny for App\Livewire component actions, with each
 *                   component naming the actions that only read
 */
final class ReadOnlyAccess
{
    public const ROLE = 'viewer';

    /** Gate abilities that only read; everything else is a write. */
    public const READ_ABILITIES = ['view', 'viewAny'];

    /** Rights over one's own account, not over anyone's company data. */
    public const OWN_ACCOUNT_ABILITIES = ['export-own-data', 'erase-own-data'];

    /** Livewire calls that never change anything (pagination, lazy loading). */
    public const LIVEWIRE_FRAMEWORK_CALLS = ['gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage', '__lazyLoad'];

    public static function applies(?Authenticatable $user): bool
    {
        return $user instanceof User
            && (self::viewerOnCurrentTeam($user) || self::viewerInCurrentOrganization($user));
    }

    /**
     * Whether the Gate must refuse this ability for a read-only person. Reading
     * abilities, rights over one's own account, and the state of one's own
     * notifications stay allowed.
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function deniesAbility(User $user, string $ability, array $arguments): bool
    {
        if (in_array($ability, [...self::READ_ABILITIES, ...self::OWN_ACCOUNT_ABILITIES], true)) {
            return false;
        }

        $subject = $arguments[0] ?? null;

        if ($subject instanceof PlatformNotification && (int) $subject->user_id === (int) $user->id) {
            return false;
        }

        return self::applies($user);
    }

    /** May a read-only person invoke this action on this App\Livewire component? */
    public static function permitsLivewireCall(Component $component, string $method): bool
    {
        if (! str_starts_with($component::class, 'App\\Livewire\\')) {
            return true; // Jetstream/Fortify account screens: authorized by their own policies
        }

        if (in_array($method, self::LIVEWIRE_FRAMEWORK_CALLS, true)) {
            return true;
        }

        $declared = $component::class.'::READ_ONLY_ACTIONS';

        return defined($declared) && in_array($method, constant($declared), true);
    }

    private static function viewerOnCurrentTeam(User $user): bool
    {
        if (! $user->current_team_id) {
            return false;
        }

        // Jetstream's pivot is whatever Jetstream::membershipModel() says (here App\Models\Membership).
        $pivot = (new (Jetstream::membershipModel()))->getTable();

        return DB::table($pivot)
            ->join('teams', 'teams.id', '=', "{$pivot}.team_id")
            ->where("{$pivot}.team_id", $user->current_team_id)
            ->where("{$pivot}.user_id", $user->id)
            ->where("{$pivot}.role", self::ROLE)
            ->where('teams.user_id', '!=', $user->id) // the owner is never a viewer
            ->exists();
    }

    private static function viewerInCurrentOrganization(User $user): bool
    {
        $organizationId = session('current_organization_id');

        if (! $organizationId) {
            return false;
        }

        return DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->where('role', self::ROLE)
            ->exists();
    }
}
