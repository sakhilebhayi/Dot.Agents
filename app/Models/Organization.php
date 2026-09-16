<?php

namespace App\Models;

use App\Support\TaggableCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'logo', 'industry', 'size',
        'country', 'timezone', 'currency', 'plan', 'status',
        'settings', 'billing_address', 'trial_ends_at',
        'subscription_ends_at', 'owner_id', 'stripe_customer_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'billing_address' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'department', 'job_title', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function socialCredentials(): HasMany
    {
        return $this->hasMany(OrganizationSocialCredential::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function agentDeployments(): HasMany
    {
        return $this->hasMany(AgentDeployment::class);
    }

    public function activeDeployments(): HasMany
    {
        return $this->hasMany(AgentDeployment::class)->where('status', 'active');
    }

    public function knowledgeBases(): HasMany
    {
        return $this->hasMany(KnowledgeBase::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(OrganizationSubscription::class)
            ->where('status', 'active')
            ->latest();
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(AgentWorkflow::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isSubscriptionActive(): bool
    {
        return $this->subscription_ends_at && $this->subscription_ends_at->isFuture();
    }

    /**
     * Return cached organization settings — avoids repeated DB lookups per request.
     * Cache TTL: 5 minutes; invalidated on save.
     */
    public function cachedSettings(): array
    {
        return TaggableCache::remember(
            ['org_settings'],
            "org_settings:{$this->id}",
            300,
            fn () => $this->settings ?? []
        );
    }

    protected static function boot(): void
    {
        parent::boot();
        // Invalidate org settings cache when org is updated
        static::saved(function (self $org) {
            TaggableCache::forget(['org_settings'], "org_settings:{$org->id}");
        });
    }
}
