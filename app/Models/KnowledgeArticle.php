<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeArticle extends Model
{
    use HasFactory, HasOrganizationScope, SoftDeletes;

    protected $fillable = [
        'knowledge_base_id', 'organization_id', 'created_by',
        'title', 'slug', 'content', 'summary', 'tags',
        'category', 'is_published', 'source_type', 'source_url',
        'relevance_score', 'view_count', 'last_reviewed_at', 'file_path', 'file_type', 'file_name',
        'index_status', 'index_error', 'indexed_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
        'view_count' => 'integer',
        'last_reviewed_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];

    /** @return HasMany<KnowledgeChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
