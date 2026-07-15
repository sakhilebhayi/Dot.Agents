<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Organization $organization,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice #{$this->invoice->invoice_number} — Dot.Agents",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'invoiceNumber' => $this->invoice->invoice_number,
                'orgName' => $this->organization->name,
                'amount' => number_format($this->invoice->total_amount / 100, 2),
                'currency' => strtoupper($this->invoice->currency ?? 'USD'),
                'dueDate' => $this->invoice->due_date,
                'billingPeriod' => $this->invoice->billing_period_label ?? '',
                'invoiceUrl' => url("/billing/invoices/{$this->invoice->id}"),
                'lineItems' => collect($this->invoice->line_items ?? [])
                    ->map(fn ($item) => [
                        'description' => $item['description'] ?? 'Service',
                        'amount' => $item['amount'] ?? 0,
                    ])
                    ->all(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
