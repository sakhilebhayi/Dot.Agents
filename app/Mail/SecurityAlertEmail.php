<?php

namespace App\Mail;

use App\Models\SecurityEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlertEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SecurityEvent $event,
    ) {}

    public function envelope(): Envelope
    {
        $severity = strtoupper($this->event->severity ?? 'MEDIUM');

        return new Envelope(
            subject: "[{$severity} SECURITY ALERT] {$this->event->event_type} — Dot.Agents Platform",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.security-alert',
            with: [
                'eventType' => $this->event->event_type,
                'severity' => $this->event->severity ?? 'medium',
                'description' => $this->event->description ?? '',
                'detectedAt' => $this->event->created_at,
                'reviewUrl' => url('/security/events/'.$this->event->id),
                'organizationName' => $this->event->organization->name ?? 'Platform-Wide',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
