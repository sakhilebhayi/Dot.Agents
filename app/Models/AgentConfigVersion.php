<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use App\Services\Agents\AgentConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One numbered setup of an agent. A draft can change. Once published it never does: runs pin the version they used, so
 * history, rollback and regression checks all rest on this row staying exactly as it was.
 */
class AgentConfigVersion extends Model
{
    use HasOrganizationScope;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const RETIRED = 'retired';

    protected $fillable = ['organization_id', 'agent_deployment_id', 'number', 'state', 'config', 'note', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'published_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version) {
            if ($version->getOriginal('state') !== self::DRAFT && $version->isDirty('config')) {
                throw new LogicException('A published version cannot change. Make a new draft.');
            }
        });
    }

    /** @return BelongsTo<AgentDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(AgentDeployment::class, 'agent_deployment_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return AgentConfig::normalise($this->config ?? []);
    }
}
