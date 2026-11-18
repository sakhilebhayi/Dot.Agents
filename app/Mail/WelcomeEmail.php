<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\User;
use App\Support\CentralSignIn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Organization $organization,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Dot.Agents — Your AI Workforce is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'userName' => $this->user->name,
                'orgName' => $this->organization->name,
                'loginUrl' => CentralSignIn::active() ? CentralSignIn::login() : url('/login'),
                'docsUrl' => url('/docs'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
