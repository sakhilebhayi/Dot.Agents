<div class="bell-host">
    {{-- dot-ui owns the open state (click, Escape, outside click, one menu at a time); this component only supplies the unread count and the list. --}}
    <x-dot.bell :count="$this->unreadCount" id="notifications-panel">
        <div class="dh-panel__head">
            <span class="dh-panel__title">Notifications</span>
            @if($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="dh-mark">Mark all read</button>
            @endif
        </div>

        @forelse($this->notifications as $notification)
            <div wire:key="notification-{{ $notification->id }}" class="dh-panel__item bell-item" @unless($notification->read_at) data-unread @endunless>
                <div class="bell-text">
                    <b>{{ $notification->title }}</b>
                    <p class="bell-body">{{ $notification->body }}</p>
                    <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                </div>
                @unless($notification->read_at)
                    <button type="button" wire:click="markAsRead({{ $notification->id }})" class="dh-mark">Mark as read<span class="sr">: {{ $notification->title }}</span></button>
                @endunless
            </div>
        @empty
            <div class="dh-panel__empty">No notifications yet.</div>
        @endforelse
    </x-dot.bell>
</div>
