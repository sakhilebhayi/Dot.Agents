<?php

namespace App\Notifications;

use App\Models\AgentTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentTaskCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AgentTask $task,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task Completed — {$this->task->title}")
            ->line("Your AI agent has completed the task: **{$this->task->title}**")
            ->line("Confidence: {$this->task->confidence_score}%")
            ->action('View Results', url("/tasks/{$this->task->id}"))
            ->line('Dot.Agents AI Workforce Platform');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_completed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'deployment_id' => $this->task->agent_deployment_id,
            'confidence_score' => $this->task->confidence_score,
            'completed_at' => $this->task->completed_at?->toISOString(),
            'url' => url("/tasks/{$this->task->id}"),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
