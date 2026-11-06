<div class="bell" x-data @click.outside="$wire.open && $wire.toggle()" @keydown.escape.window="$wire.open && $wire.toggle()">
    <button type="button" wire:click="toggle" @class(['btn', 'quiet', 'is-on' => $open]) aria-expanded="{{ $open ? 'true' : 'false' }}">
        Notifications
        @if($this->unreadCount > 0)
            <span class="bell-count"><span class="sr">, </span>{{ $this->unreadCount }}<span class="sr"> unread</span></span>
        @endif
    </button>

    @if($open)
        <div class="card bell-panel" role="region" aria-label="Notifications">
            <div class="card-head">
                <h2>Notifications</h2>
                @if($this->unreadCount > 0)
                    <button type="button" wire:click="markAllAsRead" class="btn quiet small">Mark all read</button>
                @endif
            </div>

            @forelse($this->notifications as $notification)
                <div wire:key="notification-{{ $notification->id }}" @class(['row', 'bell-item', 'is-unread' => ! $notification->read_at])>
                    <div class="bell-text">
                        <b>{{ $notification->title }}</b>
                        <p class="bell-body">{{ $notification->body }}</p>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                    @unless($notification->read_at)
                        <button type="button" wire:click="markAsRead({{ $notification->id }})" class="btn quiet small">Mark as read<span class="sr">: {{ $notification->title }}</span></button>
                    @endunless
                </div>
            @empty
                <div class="empty">No notifications yet.</div>
            @endforelse
        </div>
    @endif
</div>
