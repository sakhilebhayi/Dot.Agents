<?php

namespace App\Models;

use App\Models\Concerns\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What starts work without a person opening a chat.
 *
 * @property array<string, mixed>|null $settings
 * @property ?string $secret
 * @property \Illuminate\Support\Carbon|null $next_run_at
 */
class AgentTrigger extends Model
{
    use HasOrganizationScope;

    public const SCHEDULE = 'schedule';

    public const WEBHOOK = 'webhook';

    public const EVENT = 'event';

    public const AGENT = 'agent';

    public const WORKFLOW = 'workflow';

    /** Things that happen on this platform that can start work. */
    public const EVENTS = ['knowledge.added' => 'A document is added to Knowledge', 'run.failed' => 'An agent fails', 'workflow.finished' => 'A workflow finishes'];

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected static function booted(): void
    {
        static::creating(fn (self $trigger) => $trigger->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['settings' => 'array', 'secret' => 'encrypted', 'is_active' => 'boolean', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
