<?php

namespace App\Models;

use App\Support\TaggableCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class AgentSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'key',
        'name',
        'description',
        'layer',
        'category',
        'department',
        'agent_type',
        'class',
        'webhook_url',
        'webhook_headers',
        'webhook_timeout_seconds',
        'manifest',
        'required_permissions',
        'required_data_sources',
        'governance_rules',
        'confidence_score',
        'sla_target',
        'output_type',
        'risk_level',
        'approval_required',
        'audit_required',
        'delegation_capable',
        'is_active',
        'is_built_in',
        'requires_ai',
        'sort_order',
    ];

    protected $casts = [
        'webhook_headers' => 'array',
        'manifest' => 'array',
        'required_permissions' => 'array',
        'required_data_sources' => 'array',
        'governance_rules' => 'array',
        'is_active' => 'boolean',
        'is_built_in' => 'boolean',
        'requires_ai' => 'boolean',
        'approval_required' => 'boolean',
        'audit_required' => 'boolean',
        'delegation_capable' => 'boolean',
        'confidence_score' => 'integer',
    ];

    // ── Risk levels ──────────────────────────────────────

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_CRITICAL = 'critical';

    // ── Output types ─────────────────────────────────────

    public const OUTPUT_REPORT = 'report';

    public const OUTPUT_ACTION = 'action';

    public const OUTPUT_NOTIFICATION = 'notification';

    public const OUTPUT_RECORD = 'record';

    public const OUTPUT_APPROVAL_REQUEST = 'approval_request';

    public const OUTPUT_DATA = 'data';

    // ── Scopes ──────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLayer($query, string $layer)
    {
        return $query->where('layer', $layer);
    }

    public function scopeForDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByRisk($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    public function scopeRequiresApproval($query)
    {
        return $query->where('approval_required', true);
    }

    public function scopeBuiltIn($query)
    {
        return $query->where('is_built_in', true);
    }

    /** Skills visible to an organization: the shared platform catalog plus its own custom skills. */
    public function scopeVisibleTo($query, int $organizationId)
    {
        return $query->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organizationId));
    }

    // ── Relationships ────────────────────────────────────

    /** Null for platform-wide catalog skills -- see scopePlatformCatalog(). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AgentSkillAssignment::class, 'skill_id');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AgentSkillExecution::class, 'skill_id');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AgentSkillPermission::class, 'skill_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(AgentSkillRequirement::class, 'skill_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AgentSkillApproval::class, 'skill_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AgentSkillAudit::class, 'skill_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AgentSkillScore::class, 'skill_id');
    }

    // ── Helpers ──────────────────────────────────────────

    /** True when this skill has a PHP implementation class or a webhook to invoke. */
    public function hasImplementation(): bool
    {
        return (! empty($this->class) && class_exists($this->class)) || $this->isWebhookSkill();
    }

    /** True for an org-defined custom skill executed by calling out to its own endpoint. */
    public function isWebhookSkill(): bool
    {
        return ! empty($this->webhook_url);
    }

    /** Whether this skill can execute given a risk level and approval state. */
    public function canExecuteWithoutApproval(): bool
    {
        return ! $this->approval_required
            || in_array($this->risk_level, [self::RISK_LOW]);
    }

    /** Layer display label. */
    public function layerLabel(): string
    {
        return match ($this->layer) {
            'core' => 'Core Worker',
            'enterprise' => 'Enterprise Decision',
            'workforce' => 'Workforce',
            'governance' => 'Self-Governance',
            'platform' => 'Platform Intelligence',
            'meta' => 'Meta-Agent',
            default => ucfirst($this->layer),
        };
    }

    /** Layer badge color (Tailwind CSS class). */
    public function layerColor(): string
    {
        return match ($this->layer) {
            'core' => 'blue',
            'enterprise' => 'purple',
            'workforce' => 'yellow',
            'governance' => 'green',
            'platform' => 'orange',
            'meta' => 'red',
            default => 'gray',
        };
    }

    /** Risk badge color. */
    public function riskColor(): string
    {
        return match ($this->risk_level) {
            self::RISK_LOW => 'green',
            self::RISK_MEDIUM => 'yellow',
            self::RISK_HIGH => 'orange',
            self::RISK_CRITICAL => 'red',
            default => 'gray',
        };
    }

    /** Risk badge label. */
    public function riskLabel(): string
    {
        return match ($this->risk_level) {
            self::RISK_LOW => 'Low Risk',
            self::RISK_MEDIUM => 'Medium Risk',
            self::RISK_HIGH => 'High Risk',
            self::RISK_CRITICAL => 'Critical Risk',
            default => ucfirst($this->risk_level),
        };
    }

    protected static function boot(): void
    {
        parent::boot();
        // Invalidate skill catalog cache when skills change
        static::saved(fn () => TaggableCache::flush(['skills', 'catalog']));
        static::deleted(fn () => TaggableCache::flush(['skills', 'catalog']));
    }

    /**
     * Named scope that documents this model is an intentionally shared
     * platform-level catalog — NOT org-scoped. Skills are global definitions;
     * org-specific assignments live in AgentSkillAssignment.
     *
     * Usage: AgentSkill::platformCatalog()->where('layer', 'core')->get();
     */
    public function scopePlatformCatalog($query)
    {
        return $query; // Intentionally shared — no organization_id filter
    }
}
