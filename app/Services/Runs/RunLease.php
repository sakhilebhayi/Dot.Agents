<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use Illuminate\Support\Str;

/** Only one process works a run at a time. A lease that expires is how a dead worker hands a run back. */
class RunLease
{
    public function take(int $runId): ?string
    {
        $owner = (string) Str::ulid();

        $taken = AgentRun::whereKey($runId)
            ->whereIn('status', [AgentRun::QUEUED, AgentRun::RUNNING])
            ->where(fn ($query) => $query->whereNull('leased_until')->orWhere('leased_until', '<', now()))
            ->update(['leased_until' => now()->addSeconds((int) config('agents.lease_seconds')), 'lease_owner' => $owner]);

        return $taken === 1 ? $owner : null;
    }

    public function give(int $runId, string $owner): void
    {
        AgentRun::whereKey($runId)->where('lease_owner', $owner)->update(['leased_until' => null, 'lease_owner' => null]);
    }
}
