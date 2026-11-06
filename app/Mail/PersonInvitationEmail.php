<?php

namespace App\Mail;

use App\Services\Organizations\PersonInvitations;
use App\Support\OrgRoles;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PersonInvitationEmail extends Mailable
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $invitedBy,
        public readonly string $role,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Join {$this->businessName} on Dot.Agents");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.person-invitation', with: [
            'roleLabel' => OrgRoles::LABELS[$this->role] ?? 'Member',
            'days' => PersonInvitations::DAYS_VALID,
        ]);
    }
}
