<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelledEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Subscription cancelled — Dot.Agents',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-cancelled',
            with: [
                'orgName' => $this->organization->name,
                'reactivateUrl' => url('/billing/reactivate'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
