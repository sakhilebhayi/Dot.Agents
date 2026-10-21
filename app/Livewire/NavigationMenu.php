<?php

namespace App\Livewire;

use Laravel\Jetstream\Http\Livewire\NavigationMenu as JetstreamNavigationMenu;

class NavigationMenu extends JetstreamNavigationMenu
{
    /** Actions a read-only (viewer) role may still call; every other action is refused for it. */
    public const READ_ONLY_ACTIONS = ['toJSON'];

    /**
     * Guard against Alpine.js $wire proxy forwarding `toJSON` as a server call.
     *
     * When JSON.stringify() is called on an Alpine data object that contains a
     * $wire proxy property, JavaScript invokes proxy.toJSON(key). Livewire 3's
     * $wire proxy does not protect the `toJSON` property access, so it falls
     * through to the server via the wireFallback mechanism. This method is the
     * PHP-side guard that prevents the MethodNotFoundException.
     *
     * @see https://github.com/livewire/livewire/issues/7468
     */
    public function toJSON(): void
    {
        // No-op: prevent Livewire from throwing MethodNotFoundException.
    }
}
