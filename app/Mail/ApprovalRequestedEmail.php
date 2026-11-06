<?php

namespace App\Mail;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalRequestedEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AgentApproval $approval,
        public readonly AgentDeployment $deployment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Action Required] AI Agent Approval Requested — {$this->deployment->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.approval-requested',
            with: [
                'deploymentName' => $this->deployment->name,
                'agentName' => $this->deployment->agent->name ?? 'Agent',
                'taskDescription' => $this->approval->title,
                'reviewUrl' => route('governance.approvals', ['approval' => $this->approval->uuid]),
                'expiresAt' => $this->approval->expires_at,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
