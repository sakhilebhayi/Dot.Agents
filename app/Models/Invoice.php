<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory, HasOrganizationScope;

    protected $fillable = [
        'uuid', 'organization_id', 'invoice_number', 'stripe_invoice_id',
        'status', 'subtotal', 'tax', 'total', 'currency',
        'line_items', 'invoice_date', 'due_date', 'paid_at',
        'payment_method', 'pdf_url', 'billing_details',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'billing_address' => 'array',
        'line_items' => 'array',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(OrganizationSubscription::class, 'organization_subscription_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_date?->isPast();
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
