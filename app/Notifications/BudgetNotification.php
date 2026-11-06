<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BudgetNotification extends Notification
{
    public function __construct(private readonly float $spent, private readonly float $limit) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'budget_warning', 'spent' => round($this->spent, 2), 'limit' => $this->limit, 'url' => route('usage')];
    }
}
