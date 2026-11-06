<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\CancelRunData;
use App\Jobs\RunAgentStep;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Gate;

/** Cancelling asks the run to stop at its next step. A step already running is finished and written down first. */
class CancelRunAction
{
    public function execute(AgentRun $run, CancelRunData $data): AgentRun
    {
        Gate::forUser($data->actor)->authorize('cancel', $run);

        if ($run->isFinished()) {
            return $run;
        }

        $run->update([
            'cancel_requested_at' => now(),
            // A run waiting on a person has no worker, so it needs one to notice.
            'status' => $run->status === AgentRun::WAITING_APPROVAL ? AgentRun::RUNNING : $run->status,
        ]);

        RunAgentStep::dispatch($run->id, $run->organization_id);

        return $run;
    }
}
