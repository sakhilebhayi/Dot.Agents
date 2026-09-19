<?php

namespace App\Jobs;

use App\Models\PlatformNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPlatformNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly int $organizationId,
        public readonly string $type,
        public readonly string $title,
        public readonly string $message,
        public readonly string $severity = 'info',
        public readonly array $data = [],
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        // Idempotency: skip if a matching unread notification already exists within 5 min
        $exists = PlatformNotification::where('user_id', $this->userId)
            ->where('type', $this->type)
            ->where('title', $this->title)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($exists) {
            return;
        }

        PlatformNotification::create([
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->message,
            'priority' => match ($this->severity) {
                'critical' => 'urgent',
                'error', 'high' => 'high',
                'info', 'success' => 'low',
                default => 'normal',
            },
            'data' => $this->data,
            'action_url' => $this->actionUrl,
        ]);
    }

    public static function toAdmins(
        int $organizationId,
        string $type,
        string $title,
        string $message,
        string $severity = 'warning',
        array $data = [],
        ?string $actionUrl = null
    ): void {
        $admins = User::whereHas('organizations', fn ($q) => $q
            ->where('organizations.id', $organizationId)
            ->whereIn('organization_user.role', ['owner', 'admin'])
        )
            ->select('id')
            ->get();

        foreach ($admins as $admin) {
            dispatch(new self(
                userId: $admin->id,
                organizationId: $organizationId,
                type: $type,
                title: $title,
                message: $message,
                severity: $severity,
                data: $data,
                actionUrl: $actionUrl
            ));
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[SendPlatformNotification] Failed to deliver notification', [
            'user_id' => $this->userId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
