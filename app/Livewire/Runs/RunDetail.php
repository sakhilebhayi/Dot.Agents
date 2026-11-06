<?php

namespace App\Livewire\Runs;

use App\Actions\Agents\CancelRunAction;
use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\CancelRunData;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Models\AgentApproval;
use App\Models\AgentRun;
use App\Services\Insights\RunDiagnosis;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** @property-read AgentRun $run */
class RunDetail extends Component
{
    public const READ_ONLY_ACTIONS = [];

    #[Locked]
    public int $runId;

    #[Computed]
    public function run(): AgentRun
    {
        return AgentRun::with(['deployment', 'configVersion', 'initiator'])->findOrFail($this->runId);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function diagnosis(): ?array
    {
        return app(RunDiagnosis::class)->for($this->run);
    }

    #[Computed]
    public function approval(): ?AgentApproval
    {
        return AgentApproval::where('agent_run_id', $this->runId)->where('status', 'pending')->first();
    }

    public function cancel(CancelRunAction $action): void
    {
        $action->execute($this->run, new CancelRunData(auth()->user()));
        unset($this->run);
    }

    /** Starts the same work again as a new run. The failed run stays on the record. */
    public function retry(StartRunAction $action): void
    {
        abort_unless(auth()->user()->canInOrganization('agents.operate', $this->run->organization_id), 403);

        try {
            $again = $action->execute($this->run->deployment, new StartRunData(
                input: (string) $this->run->input,
                triggerType: $this->run->trigger_type === 'workflow_tool' ? 'manual' : $this->run->trigger_type,
                initiatedBy: auth()->id(),
                sessionId: $this->run->agent_session_id,
                taint: $this->run->taint,
            ));
        } catch (RunRefused $e) {
            $this->addError('retry', $e->getMessage());

            return;
        }

        $this->redirectRoute('runs.show', $again);
    }

    public function refresh(): void
    {
        unset($this->run, $this->diagnosis, $this->approval);
    }

    public function render()
    {
        return view('livewire.runs.run-detail');
    }
}
