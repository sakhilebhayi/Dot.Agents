<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\RestoreAgentVersionAction;
use App\DTOs\Agents\PublishAgentVersionData;
use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** @property-read AgentDeployment $agent */
class AgentVersions extends Component
{
    public const READ_ONLY_ACTIONS = [];

    private const LABELS = ['purpose' => 'why it matters', 'instructions' => 'instructions', 'tone' => 'tone', 'language' => 'language', 'model' => 'model', 'knowledge' => 'knowledge', 'memory' => 'memory', 'tools' => 'tools', 'autonomy' => 'autonomy', 'limits' => 'limits', 'errors' => 'failure handling', 'output' => 'result format', 'reporting' => 'reporting'];

    #[Locked]
    public int $deploymentId;

    #[Computed]
    public function agent(): AgentDeployment
    {
        return AgentDeployment::findOrFail($this->deploymentId);
    }

    /** @return Collection<int, array{version: AgentConfigVersion, changed: list<string>}> Newest first, each with what it changed. */
    #[Computed]
    public function versions(): Collection
    {
        $versions = $this->agent->versions()->orderBy('number')->get()->values();

        return $versions->map(fn (AgentConfigVersion $version, int $index) => [
            'version' => $version,
            'changed' => $index === 0 ? [] : $this->changes($versions[$index - 1], $version),
        ])->reverse()->take(10)->values();
    }

    public function restore(int $id, RestoreAgentVersionAction $action): void
    {
        $action->execute($this->agent->versions()->findOrFail($id), new PublishAgentVersionData(auth()->id()));

        unset($this->agent, $this->versions);
        session()->flash('versions', 'Restored as a new version.');
    }

    /** @return list<string> */
    private function changes(AgentConfigVersion $before, AgentConfigVersion $after): array
    {
        $old = $before->settings();
        $new = $after->settings();

        return array_values(array_map(fn ($key) => self::LABELS[$key] ?? $key, array_filter(array_keys($new), fn ($key) => ($old[$key] ?? null) != $new[$key])));
    }

    public function render()
    {
        return view('livewire.agents.agent-versions');
    }
}
