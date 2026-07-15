<?php

namespace App\Mail;

use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialEndingEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly ?string $trialEndsAt = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your trial ends soon — keep your AI Workforce running',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.trial-ending',
            with: [
                'orgName' => $this->organization->name,
                'trialEndsAt' => $this->trialEndsAt
                    ? Carbon::parse($this->trialEndsAt)->format('M j, Y')
                    : null,
                'upgradeUrl' => url('/billing/upgrade'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
