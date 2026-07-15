<?php

namespace App\Notifications;

use App\Mail\InvoiceEmail;
use App\Mail\PaymentFailedEmail;
use App\Mail\SubscriptionCancelledEmail;
use App\Mail\TrialEndingEmail;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $billingEvent,
        private readonly Organization $organization,
        private readonly ?Invoice $invoice = null,
        private readonly array $context = [],
    ) {
        $this->onQueue('billing');
    }

    public function via(object $notifiable): array
    {
        return match ($this->billingEvent) {
            'invoice_created', 'payment_failed' => ['mail', 'database', 'broadcast'],
            'trial_ending', 'subscription_cancelled' => ['mail', 'database'],
            default => ['database'],
        };
    }

    public function toMail(object $notifiable): mixed
    {
        $email = $notifiable->routeNotificationFor('mail', $this) ?? $notifiable->email;

        return match ($this->billingEvent) {
            'invoice_created' => $this->invoice
                ? (new InvoiceEmail($this->invoice, $this->organization))->to($email)
                : $this->genericMail(),
            'payment_failed' => (new PaymentFailedEmail($this->organization))->to($email),
            'trial_ending' => (new TrialEndingEmail(
                $this->organization,
                $this->context['trial_ends_at'] ?? null,
            ))->to($email),
            'subscription_cancelled' => (new SubscriptionCancelledEmail($this->organization))->to($email),
            default => $this->genericMail(),
        };
    }

    private function genericMail(): MailMessage
    {
        return (new MailMessage)
            ->subject($this->resolveSubject())
            ->line($this->resolveBody())
            ->action('Manage Billing', url('/billing'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'billing',
            'billing_event' => $this->billingEvent,
            'organization_id' => $this->organization->id,
            'invoice_id' => $this->invoice?->id,
            'context' => $this->context,
            'url' => url('/billing'),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    private function resolveSubject(): string
    {
        return match ($this->billingEvent) {
            'payment_failed' => '[Action Required] Payment Failed — Dot.Agents',
            'trial_ending' => 'Your trial ends soon — upgrade to keep your AI Workforce',
            'subscription_cancelled' => 'Subscription cancelled — Dot.Agents',
            default => 'Billing Update — Dot.Agents',
        };
    }

    private function resolveBody(): string
    {
        return match ($this->billingEvent) {
            'payment_failed' => "We couldn't process your payment for {$this->organization->name}. Please update your payment method.",
            'trial_ending' => "Your free trial for {$this->organization->name} is ending soon. Upgrade to continue using your AI workforce.",
            'subscription_cancelled' => "Your subscription for {$this->organization->name} has been cancelled.",
            default => "A billing update is available for {$this->organization->name}.",
        };
    }
}
