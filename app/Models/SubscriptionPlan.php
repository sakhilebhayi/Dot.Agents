<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'billing_cycle', 'price', 'yearly_price',
        'max_agents', 'max_users', 'max_departments', 'max_workflows',
        'monthly_token_quota', 'features', 'limits', 'is_active', 'is_featured',
        'is_public', 'sort_order', 'stripe_price_id', 'stripe_product_id', 'trial_days',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'max_agents' => 'integer',
        'max_users' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Named scope that documents this model is an intentionally shared
     * platform-level catalog — NOT org-scoped. Subscription plans are global
     * product definitions; org subscriptions are stored in Subscription model.
     *
     * Usage: SubscriptionPlan::platformCatalog()->public()->ordered()->get();
     */
    public function scopePlatformCatalog($query)
    {
        return $query; // Intentionally shared — no organization_id filter
    }
}
