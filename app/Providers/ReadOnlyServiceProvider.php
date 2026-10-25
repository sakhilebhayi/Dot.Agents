<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ReadOnlyAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;

use function Livewire\on;

/**
 * Wires the read-only (viewer) role into the Gate, Livewire and Blade.
 * The route layer is the `writable` middleware alias (bootstrap/app.php).
 */
class ReadOnlyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A non-null result wins, so only ever return false (deny) or null (no opinion).
        Gate::before(function ($user, string $ability, array $arguments = []) {
            return $user instanceof User && ReadOnlyAccess::deniesAbility($user, $ability, $arguments) ? false : null;
        });

        // Default-deny for component actions: a new action is refused for viewers
        // until its component lists it in READ_ONLY_ACTIONS.
        on('call', function ($component, string $method) {
            $user = auth()->user();

            if ($component instanceof Component && $user instanceof User
                && ReadOnlyAccess::applies($user)
                && ! ReadOnlyAccess::permitsLivewireCall($component, $method)) {
                abort(403, 'Your role is read-only.');
            }
        });

        // @writable ... @endwritable hides write controls; the server checks above are what count.
        Blade::if('writable', fn () => ! ReadOnlyAccess::applies(auth()->user()));
    }
}
