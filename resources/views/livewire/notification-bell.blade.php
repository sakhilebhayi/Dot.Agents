<div class="relative" x-data @click.outside="$wire.open = false">
    <button wire:click="toggle"
            class="relative text-[#909088] hover:text-[#111111] dark:hover:text-white p-2 rounded transition-colors"
            aria-label="Notifications">
        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($this->unreadCount > 0)
            <span class="absolute top-1 right-1 w-1.5 h-1.5 bg-red-500 rounded-full"></span>
        @endif
    </button>

    @if($open)
        <div class="absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto rounded-lg border border-[#e8e8e2] dark:border-gray-800
                    bg-white dark:bg-gray-900 shadow-lg z-50">
            <div class="flex items-center justify-between px-4 py-3 border-b border-[#e8e8e2] dark:border-gray-800">
                <span class="text-sm font-semibold text-[#111111] dark:text-white">Notifications</span>
                @if($this->unreadCount > 0)
                    <button wire:click="markAllAsRead" class="text-xs font-medium text-brand-purple hover:underline">
                        Mark all read
                    </button>
                @endif
            </div>

            @forelse($this->notifications as $notification)
                <div wire:key="notification-{{ $notification->id }}"
                     class="flex items-start gap-3 px-4 py-3 border-b border-[#e8e8e2] dark:border-gray-800 last:border-0
                            {{ $notification->read_at ? '' : 'bg-brand-purple/5 dark:bg-brand-purple/10' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[#111111] dark:text-white truncate">{{ $notification->title }}</p>
                        <p class="text-xs text-[#909088] dark:text-gray-400 mt-0.5 line-clamp-2">{{ $notification->body }}</p>
                        <p class="text-2xs text-[#909088] dark:text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    @unless($notification->read_at)
                        <button wire:click="markAsRead({{ $notification->id }})"
                                class="flex-shrink-0 w-2 h-2 rounded-full bg-brand-purple mt-1.5"
                                aria-label="Mark as read"></button>
                    @endunless
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-[#909088] dark:text-gray-400">
                    No notifications yet.
                </div>
            @endforelse
        </div>
    @endif
</div>
