<?php

namespace App\Models;

use App\Events\AgentCapabilityContractChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AgentVersion — platform-level catalog resource.
 *
 * Versioned snapshots of an Agent's configuration and capabilities.
 * This model belongs to Agent (a platform catalog item) and is NOT
 * tenant-scoped itself. Tenant isolation is enforced at the AgentDeployment
 * layer: every deployment references an AgentVersion and is scoped to an
 * organization_id. Querying AgentVersions in isolation (e.g. the marketplace
 * browse page) is intentionally cross-tenant — users see all published versions.
 *
 * @see AgentDeployment — the tenant-scoped join between an org and a version
 */
class AgentVersion extends Model
{
    protected $fillable = [
        'agent_id', 'created_by', 'version', 'status',
        'release_notes', 'config_snapshot', 'capabilities_snapshot',
        'is_current', 'published_at', 'deprecated_at',
    ];

    protected $casts = [
        'config_snapshot' => 'array',
        'capabilities_snapshot' => 'array',
        'is_current' => 'boolean',
        'published_at' => 'datetime',
        'deprecated_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(AgentDeployment::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Publish this version and demote the previously current version.
     * Fires AgentCapabilityContractChanged when breaking capability changes
     * are detected, triggering a governance review workflow.
     */
    public function publish(): void
    {
        $previousVersion = static::where('agent_id', $this->agent_id)
            ->where('is_current', true)
            ->first();

        // Demote existing current version
        static::where('agent_id', $this->agent_id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $this->update([
            'status' => 'published',
            'is_current' => true,
            'published_at' => now(),
        ]);

        // Fire governance event when capability contract breaks backward compatibility
        if ($previousVersion && $this->hasBreakingCapabilityChanges(
            $previousVersion->capabilities_snapshot ?? [],
            $this->capabilities_snapshot ?? []
        )) {
            event(new AgentCapabilityContractChanged($this, $previousVersion));
        }
    }

    /**
     * Deprecate this version (no longer recommended for new deployments).
     */
    public function deprecate(): void
    {
        $this->update([
            'status' => 'deprecated',
            'is_current' => false,
            'deprecated_at' => now(),
        ]);
    }

    /**
     * Detect breaking capability changes between two capability snapshots.
     *
     * A change is considered breaking when:
     *  - A capability key present in the previous version is absent in the new one
     *    (removed capabilities break existing deployments that depend on them)
     *  - A capability's required inputs change type (existing callers may break)
     *
     * Additive changes (new capabilities in the new version) are non-breaking.
     */
    public function hasBreakingCapabilityChanges(array $previous, array $current): bool
    {
        if (empty($previous)) {
            return false;
        }

        $previousKeys = array_keys($previous);
        $currentKeys = array_keys($current);

        // Removed capability keys are always breaking
        $removedKeys = array_diff($previousKeys, $currentKeys);
        if (! empty($removedKeys)) {
            return true;
        }

        // Check whether any shared capability's input types changed
        foreach ($previousKeys as $key) {
            if (! isset($current[$key])) {
                continue;
            }

            $prevInputType = $previous[$key]['input_type'] ?? null;
            $currInputType = $current[$key]['input_type'] ?? null;

            if ($prevInputType !== null && $prevInputType !== $currInputType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Named scope that documents this model is a platform-level version
     * catalog scoped to an Agent, not to an Organization. Deployments
     * reference versions via agent_version_id — access control is enforced
     * at the deployment level, not here.
     *
     * Usage: AgentVersion::platformCatalog()->where('agent_id', $id)->get();
     */
    public function scopePlatformCatalog($query)
    {
        return $query; // Intentionally shared — scoped by agent_id, not org_id
    }
}
