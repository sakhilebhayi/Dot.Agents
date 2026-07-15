<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Action Required] Payment Failed — Dot.Agents',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
            with: [
                'orgName' => $this->organization->name,
                'billingUrl' => url('/billing'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
