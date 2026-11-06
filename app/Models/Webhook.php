<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory, HasOrganizationScope;

    protected $fillable = [
        'uuid', 'organization_id', 'created_by', 'name', 'url',
        'secret', 'events', 'is_active', 'retry_limit',
        'timeout_seconds', 'last_triggered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $webhook) {
            if (empty($webhook->uuid)) {
                $webhook->uuid = (string) Str::uuid();
            }
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(64);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true)
            || in_array('*', $this->events ?? [], true);
    }

    /**
     * Generate the HMAC-SHA256 signature for a payload.
     * Used by WebhookService to sign outgoing requests.
     */
    public function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
