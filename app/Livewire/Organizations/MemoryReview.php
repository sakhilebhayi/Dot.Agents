<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\GovernMemoryAction;
use App\DTOs\Organizations\GovernMemoryData;
use App\Models\AgentMemory;
use App\Support\TenantContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MemoryReview extends Component
{
    use WithPagination;

    public const READ_ONLY_ACTIONS = ['show', 'cancel'];

    #[Url(as: 'tab', except: 'proposed')]
    public string $status = 'proposed';

    public ?int $editing = null;

    public string $correction = '';

    #[Computed]
    public function memories()
    {
        return AgentMemory::with('deployment')
            ->where('organization_id', TenantContext::id())
            ->where('status', $this->status)
            ->latest('id')->paginate(25);
    }

    #[Computed]
    public function proposedCount(): int
    {
        return AgentMemory::where('organization_id', TenantContext::id())->where('status', AgentMemory::PROPOSED)->count();
    }

    public function show(string $status): void
    {
        $this->status = $status === AgentMemory::ACTIVE ? $status : AgentMemory::PROPOSED;
        $this->resetPage();
    }

    public function accept(int $id, GovernMemoryAction $action): void
    {
        $action->accept($this->memory($id), new GovernMemoryData(auth()->user()));
        $this->changed();
    }

    public function edit(int $id): void
    {
        $this->editing = $id;
        $this->correction = (string) $this->memory($id)->content;
    }

    public function cancel(): void
    {
        $this->reset('editing', 'correction');
    }

    public function saveCorrection(GovernMemoryAction $action): void
    {
        $this->validate(['correction' => 'required|string|max:2000']);

        $action->correct($this->memory((int) $this->editing), new GovernMemoryData(auth()->user(), $this->correction));
        $this->cancel();
        $this->changed();
    }

    public function remove(int $id, GovernMemoryAction $action): void
    {
        $action->remove($this->memory($id), new GovernMemoryData(auth()->user()));
        $this->changed();
    }

    private function memory(int $id): AgentMemory
    {
        return AgentMemory::where('organization_id', TenantContext::id())->findOrFail($id);
    }

    private function changed(): void
    {
        unset($this->memories, $this->proposedCount);
    }

    public function render()
    {
        return view('livewire.organizations.memory-review');
    }
}
