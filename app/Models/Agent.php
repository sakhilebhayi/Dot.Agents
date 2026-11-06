<?php

namespace App\Models;

use App\Models\Scopes\SharedOrOwnScope;
use App\Support\TaggableCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'category_id', 'department_id', 'name', 'slug', 'tagline',
        'description', 'full_description', 'avatar', 'version', 'agent_type',
        'specialization', 'industries', 'functions', 'primary_model',
        'model_provider', 'fallback_model', 'model_config', 'capabilities',
        'limitations', 'skills', 'tools', 'integrations', 'knowledge_areas',
        'certifications', 'languages', 'goals', 'objectives', 'kpis',
        'decision_framework', 'risk_controls', 'default_deployment_mode',
        'pricing_model', 'base_price', 'price_per_message', 'price_per_task',
        'billing_cycle', 'accuracy_score', 'reliability_score',
        'satisfaction_score', 'total_deployments', 'total_tasks_completed',
        'avg_rating', 'review_count', 'status', 'is_featured', 'is_verified',
        'is_enterprise_only', 'is_beta', 'required_plan', 'tags',
        'meta_title', 'meta_description',
        'certification_score', 'trust_score', 'trust_tier', 'certified_at',
        'performance_score', 'competencies', 'cost_tier',
        'maturity_level', 'dwca_certified_at', 'dwca_certification_level',
        'organization_id', 'created_by', 'template',
    ];

    protected $casts = [
        'industries' => 'array',
        'functions' => 'array',
        'model_config' => 'array',
        'capabilities' => 'array',
        'limitations' => 'array',
        'skills' => 'array',
        'tools' => 'array',
        'integrations' => 'array',
        'knowledge_areas' => 'array',
        'certifications' => 'array',
        'languages' => 'array',
        'goals' => 'array',
        'objectives' => 'array',
        'kpis' => 'array',
        'decision_framework' => 'array',
        'risk_controls' => 'array',
        'required_plan' => 'array',
        'tags' => 'array',
        'base_price' => 'decimal:2',
        'price_per_message' => 'decimal:4',
        'price_per_task' => 'decimal:2',
        'accuracy_score' => 'decimal:2',
        'reliability_score' => 'decimal:2',
        'satisfaction_score' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'performance_score' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'is_enterprise_only' => 'boolean',
        'is_beta' => 'boolean',
        'competencies' => 'array',
        'dwca_certified_at' => 'datetime',
        'template' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new SharedOrOwnScope);
        static::creating(function (self $agent) {
            if (empty($agent->uuid)) {
                $agent->uuid = (string) Str::uuid();
            }
        });
        // Invalidate catalog cache when agents change
        static::saved(fn () => TaggableCache::flush(['agents', 'catalog']));
        static::deleted(fn () => TaggableCache::flush(['agents', 'catalog']));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AgentCategory::class);
    }

    public function agentDepartment(): BelongsTo
    {
        return $this->belongsTo(AgentDepartment::class, 'department_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(AgentPersona::class);
    }

    public function defaultPersona()
    {
        return $this->hasOne(AgentPersona::class)->where('is_default', true);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(AgentDeployment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AgentReview::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeForDepartment($query, string $departmentSlug)
    {
        return $query->whereHas('agentDepartment', fn ($q) => $q->where('slug', $departmentSlug));
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->base_price == 0) {
            return 'Free';
        }

        return '$'.number_format($this->base_price, 2).'/'.$this->billing_cycle;
    }

    public function getHealthScoreAttribute(): float
    {
        return round(
            ($this->accuracy_score + $this->reliability_score + $this->satisfaction_score) / 3,
            1
        );
    }

    public function scopeTrustAbove($query, int $min)
    {
        return $query->where('trust_score', '>=', $min);
    }

    public function scopeByCostTier($query, string $tier)
    {
        return $query->where('cost_tier', $tier);
    }

    public function scopeHasSkill($query, string $skill)
    {
        return $query->whereJsonContains('skills', $skill);
    }

    /**
     * Named scope that documents this model is an intentionally shared
     * platform-level catalog — NOT org-scoped. Records are accessible across
     * all organizations by design. Makes the access pattern explicit and
     * auditable in code reviews so the absence of an org_id filter is clear.
     *
     * Usage: Agent::platformCatalog()->where(...)->get();
     */
    public function scopePlatformCatalog($query)
    {
        return $query; // Intentionally shared — no organization_id filter
    }
}
