<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AgentMemory extends Model
{
    use HasFactory, HasOrganizationScope, SoftDeletes;

    public const SCOPE_PERSON = 'person';

    public const SCOPE_AGENT = 'agent';

    public const SCOPE_BUSINESS = 'business';

    public const ACTIVE = 'active';

    /** Waiting for a person. Nothing proposed reaches a model. */
    public const PROPOSED = 'proposed';

    protected $fillable = [
        'uuid', 'agent_deployment_id', 'organization_id', 'user_id',
        'memory_type', 'memory_category', 'subject', 'content', 'context',
        'tags', 'importance_score', 'confidence_score', 'access_count',
        'last_accessed_at', 'expires_at', 'is_verified', 'scope', 'status', 'agent_run_id', 'source',
    ];

    protected $casts = [
        'content' => 'encrypted',
        'context' => 'array',
        'tags' => 'array',
        'importance_score' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'is_verified' => 'boolean',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $memory) {
            if (empty($memory->uuid)) {
                $memory->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<AgentDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'agent_deployment_id');
    }

    public function scopeShortTerm($query)
    {
        return $query->where('memory_type', 'short_term');
    }

    public function scopeLongTerm($query)
    {
        return $query->where('memory_type', 'long_term');
    }

    public function scopeOrganizational($query)
    {
        return $query->where('memory_type', 'organizational');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
