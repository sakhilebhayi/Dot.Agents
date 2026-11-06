<?php

namespace App\Livewire\Agents;

use App\Actions\Agents\CreateAgentAction;
use App\DTOs\Agents\CreateAgentData;
use App\Models\AgentDeployment;
use App\Services\Agents\AgentConfig;
use App\Services\Agents\AgentDescriptionReader;
use App\Services\Agents\AgentPackage;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class AgentCreate extends Component
{
    use WithFileUploads;

    public const READ_ONLY_ACTIONS = ['setWay'];

    #[Url(as: 'way', except: 'describe')]
    public string $way = 'describe';

    public string $description = '';

    /** @var array<string, mixed>|null */
    public ?array $proposal = null;

    public string $name = '';

    public string $role = '';

    public string $responsibility = '';

    /** @var TemporaryUploadedFile|null */
    public $package = null;

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function templates(): array
    {
        return config('agent_templates');
    }

    public function mount(): void
    {
        abort_unless(Gate::allows('create', [AgentDeployment::class, TenantContext::id()]), 403);
    }

    public function setWay(string $way): void
    {
        $this->way = $way;
    }

    public function draft(AgentDescriptionReader $reader): void
    {
        $this->validate(['description' => 'required|string|min:20|max:4000'], ['description.min' => 'Say a little more about the work.']);

        $this->proposal = $reader->read(TenantContext::id(), $this->description);
    }

    public function acceptProposal(CreateAgentAction $action): void
    {
        $this->create($action, $this->proposal['name'], $this->proposal['role'], $this->proposal['responsibility'], [
            'purpose' => $this->proposal['purpose'],
            'instructions' => $this->proposal['instructions'],
            'autonomy' => $this->proposal['autonomy'],
            'tools' => collect($this->proposal['tools'])->map(fn ($key) => ['key' => $key, 'mode' => 'approve'])->all(),
        ]);
    }

    public function useTemplate(string $key, CreateAgentAction $action): void
    {
        $template = config("agent_templates.{$key}") ?? abort(404);

        $this->create($action, $template['name'], $template['role'], $template['responsibility'], [
            'purpose' => $template['purpose'],
            'instructions' => $template['instructions'],
            'autonomy' => $template['autonomy'],
            'tools' => $template['tools'],
        ]);
    }

    public function build(CreateAgentAction $action): void
    {
        $this->validate(['name' => 'required|string|max:100', 'role' => 'required|string|max:120', 'responsibility' => 'nullable|string|max:500']);

        $this->create($action, $this->name, $this->role, $this->responsibility ?: null, []);
    }

    public function import(CreateAgentAction $action): void
    {
        $this->validate(['package' => 'required|file|max:512']);

        try {
            $agent = AgentPackage::read((array) json_decode((string) file_get_contents($this->package->getRealPath()), true));
        } catch (InvalidArgumentException $e) {
            $this->addError('package', $e->getMessage());

            return;
        }

        $this->create($action, $agent['name'], $agent['role'], $agent['responsibility'], $agent['config']);
    }

    /** @param  array<string, mixed>  $config */
    private function create(CreateAgentAction $action, string $name, string $role, ?string $responsibility, array $config): void
    {
        $agent = $action->execute(new CreateAgentData(
            organizationId: TenantContext::id(),
            createdBy: auth()->id(),
            name: $name,
            role: $role,
            responsibility: $responsibility,
            config: AgentConfig::normalise($config),
        ));

        $this->redirectRoute('agents.setup', $agent, navigate: false);
    }

    public function render()
    {
        return view('livewire.agents.agent-create');
    }
}
