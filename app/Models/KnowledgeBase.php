<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    use HasFactory, HasOrganizationScope;

    protected $fillable = [
        'organization_id', 'name', 'description', 'type',
        'access_level', 'is_active', 'settings', 'metadata',
    ];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class);
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(KnowledgeArticle::class)->where('is_published', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
