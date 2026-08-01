<?php

namespace App\Livewire;

use App\Models\PlatformNotification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public bool $open = false;

    #[Computed]
    public function notifications()
    {
        return PlatformNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->take(8)
            ->get();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return PlatformNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = PlatformNotification::where('user_id', auth()->id())
            ->findOrFail($notificationId);

        $this->authorize('update', $notification);

        $notification->markAsRead();

        unset($this->notifications, $this->unreadCount);
    }

    #[On('notification-received')]
    public function refresh(): void
    {
        unset($this->notifications, $this->unreadCount);
    }

    public function markAllAsRead(): void
    {
        PlatformNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        unset($this->notifications, $this->unreadCount);
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
