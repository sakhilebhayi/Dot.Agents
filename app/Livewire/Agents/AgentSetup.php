<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\PublishAgentVersionAction;
use App\Actions\Agents\SaveAgentDraftAction;
use App\DTOs\Agents\PublishAgentVersionData;
use App\DTOs\Agents\SaveAgentDraftData;
use App\Models\AgentDeployment;
use App\Models\KnowledgeBase;
use App\Services\Agents\AgentConfig;
use App\Services\Agents\AgentConfigReview;
use App\Services\Models\ModelCatalog;
use App\Services\Tools\ToolCatalogue;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AgentSetup extends Component
{
    public const READ_ONLY_ACTIONS = [];

    #[Locked]
    public int $deploymentId;

    public string $name = '';

    public string $role = '';

    public string $responsibility = '';

    public ?int $ownerId = null;

    /** @var array<string, mixed> */
    public array $config = [];

    /** @var array<string, string> Tool key to "off", "auto" or "approve". */
    public array $tools = [];

    public function mount(int $deploymentId): void
    {
        $this->deploymentId = $deploymentId;
        $agent = $this->agent;

        Gate::authorize('update', $agent);

        $version = $agent->draftVersion()->first() ?? $agent->publishedVersion;
        $this->config = $version?->settings() ?? AgentConfig::defaults();
        $this->name = $agent->name;
        $this->role = (string) $agent->role;
        $this->responsibility = (string) $agent->responsibility;
        $this->ownerId = $agent->owner_id;
        $this->tools = collect($this->config['tools'])->pluck('mode', 'key')->all();
    }

    #[Computed]
    public function agent(): AgentDeployment
    {
        return AgentDeployment::findOrFail($this->deploymentId);
    }

    #[Computed]
    public function catalogue()
    {
        return collect(app(ToolCatalogue::class)->all($this->agent->organization_id))
            ->map(fn ($tool) => $tool->definition())
            ->reject(fn ($tool) => in_array($tool->key, ['knowledge_search', 'remember_fact'], true));
    }

    #[Computed]
    public function bases()
    {
        return KnowledgeBase::where('organization_id', $this->agent->organization_id)->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function people()
    {
        return $this->agent->organization->users()->orderBy('name')->get();
    }

    /** @return array<string, array<string, string>> */
    #[Computed]
    public function models(): array
    {
        return app(ModelCatalog::class)->available();
    }

    /** @return array{findings: list<array<string, string>>, verdict: string, words: string} */
    #[Computed]
    public function review(): array
    {
        $preview = $this->agent->replicate();
        $preview->id = $this->agent->id;
        $preview->fill(['responsibility' => $this->responsibility, 'owner_id' => $this->ownerId]);

        return app(AgentConfigReview::class)->review($preview, $this->assembled(), $this->agent->draftVersion()->first() ?? $this->agent->publishedVersion);
    }

    public function save(SaveAgentDraftAction $action): void
    {
        $this->validate(['name' => 'required|string|max:100', 'role' => 'nullable|string|max:120', 'responsibility' => 'nullable|string|max:500']);

        $action->execute($this->agent, new SaveAgentDraftData(
            savedBy: auth()->id(),
            config: $this->assembled(),
            name: $this->name,
            role: $this->role,
            responsibility: $this->responsibility,
            ownerId: $this->ownerId,
        ));

        unset($this->agent, $this->review);
        session()->flash('saved', 'Draft saved. What runs now has not changed.');
    }

    public function publish(SaveAgentDraftAction $save, PublishAgentVersionAction $publish): void
    {
        $this->save($save);

        $publish->execute($this->agent->draftVersion()->firstOrFail(), new PublishAgentVersionData(auth()->id()));

        unset($this->agent, $this->review);
        session()->flash('saved', 'Published. New work uses this version.');
    }

    /** @return array<string, mixed> */
    private function assembled(): array
    {
        $tools = collect($this->tools)->reject(fn ($mode) => $mode === 'off' || $mode === '')
            ->map(fn ($mode, $key) => ['key' => $key, 'mode' => $mode])->values()->all();

        $config = [...$this->config, 'tools' => $tools, 'knowledge' => array_map('intval', $this->config['knowledge'])];
        $config['autonomy'] = (int) $config['autonomy'];

        return $config;
    }

    public function render()
    {
        return view('livewire.agents.agent-setup');
    }
}
