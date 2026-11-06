<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\ChangeAgentStageAction;
use App\Actions\Agents\RunAgentTestsAction;
use App\Actions\Agents\SaveTestCaseAction;
use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\ChangeAgentStageData;
use App\DTOs\Agents\RunTestsData;
use App\DTOs\Agents\SaveTestCaseData;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentTestCase;
use App\Models\AgentTestResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read AgentDeployment $agent
 * @property-read Collection<int, AgentTestCase> $cases
 */
class AgentTestBench extends Component
{
    public const READ_ONLY_ACTIONS = [];

    #[Locked]
    public int $deploymentId;

    public string $input = '';

    public ?int $versionId = null;

    public ?int $compareId = null;

    public string $simulate = 'manual';

    public string $failTool = '';

    /** @var list<int> */
    public array $runIds = [];

    public string $caseName = '';

    public string $caseInput = '';

    public string $mustSay = '';

    public string $mustNotSay = '';

    public string $mustUse = '';

    public function mount(int $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        Gate::authorize('update', $this->agent);
        $this->versionId = ($this->agent->draftVersion()->first() ?? $this->agent->publishedVersion)?->id;
    }

    #[Computed]
    public function agent(): AgentDeployment
    {
        return AgentDeployment::findOrFail($this->deploymentId);
    }

    #[Computed]
    public function versions()
    {
        return $this->agent->versions()->orderByDesc('number')->get();
    }

    #[Computed]
    public function runs()
    {
        return AgentRun::whereIn('id', $this->runIds)->orderBy('id')->get();
    }

    #[Computed]
    public function cases()
    {
        return AgentTestCase::where('agent_deployment_id', $this->deploymentId)->orderBy('id')->get();
    }

    /** @return array<int, AgentTestResult> Latest result for each case on the chosen version. */
    #[Computed]
    public function results(): array
    {
        return AgentTestResult::with('run')->where('config_version_id', $this->versionId)->whereIn('agent_test_case_id', $this->cases->pluck('id'))
            ->orderBy('id')->get()->keyBy('agent_test_case_id')->all();
    }

    public function start(StartRunAction $action): void
    {
        $this->validate(['input' => 'required|string|max:20000']);

        $this->runIds = [];

        foreach (array_filter([$this->versionId, $this->compareId]) as $version) {
            try {
                $this->runIds[] = $action->execute($this->agent, new StartRunData(
                    input: $this->input,
                    triggerType: $this->simulate,
                    initiatedBy: auth()->id(),
                    isTest: true,
                    versionId: $version,
                    failTool: $this->failTool ?: null,
                ))->id;
            } catch (RunRefused $e) {
                $this->addError('input', $e->getMessage());
            }
        }

        unset($this->runs);
    }

    public function saveCase(SaveTestCaseAction $action): void
    {
        $this->validate(['caseName' => 'required|string|max:160', 'caseInput' => 'required|string|max:20000']);

        $action->execute($this->agent, new SaveTestCaseData(auth()->user(), $this->caseName, $this->caseInput, 'manual', [
            'contains' => $this->lines($this->mustSay),
            'not_contains' => $this->lines($this->mustNotSay),
            'uses_tools' => $this->lines($this->mustUse),
        ]));

        $this->reset('caseName', 'caseInput', 'mustSay', 'mustNotSay', 'mustUse');
        unset($this->cases);
    }

    public function removeCase(int $id, SaveTestCaseAction $action): void
    {
        $action->remove($this->agent, AgentTestCase::where('agent_deployment_id', $this->deploymentId)->findOrFail($id), new SaveTestCaseData(auth()->user(), '', '', 'manual', []));
        unset($this->cases, $this->results);
    }

    public function runCases(RunAgentTestsAction $action): void
    {
        $action->execute($this->agent, new RunTestsData(auth()->user(), (int) $this->versionId));
        unset($this->results);
    }

    public function moveTo(string $stage, ChangeAgentStageAction $action): void
    {
        $action->execute($this->agent, new ChangeAgentStageData(auth()->user(), $stage));
        unset($this->agent);
    }

    public function refresh(): void
    {
        unset($this->runs, $this->results);
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $text))));
    }

    public function render()
    {
        return view('livewire.agents.agent-test-bench');
    }
}
