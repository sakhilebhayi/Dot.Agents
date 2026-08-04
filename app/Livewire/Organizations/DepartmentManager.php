<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\DeleteDepartmentAction;
use App\Actions\Organizations\SaveDepartmentAction;
use App\DTOs\Organizations\DeleteDepartmentData;
use App\DTOs\Organizations\SaveDepartmentData;
use App\Models\Department;
use App\Models\Organization;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class DepartmentManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    #[Validate('required|string|max:255')]
    public string $formName = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $formDescription = null;

    #[Validate('nullable|string|max:100')]
    public ?string $formType = null;

    #[Validate('nullable|string|max:255')]
    public ?string $formHeadName = null;

    #[Computed]
    public function organization(): Organization
    {
        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403);

        return Organization::findOrFail($orgId);
    }

    #[Computed]
    public function departments()
    {
        // No explicit organization_id filter needed: Department's
        // HasOrganizationScope trait applies it automatically from the session.
        return Department::withCount('deployments')
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $dept = Department::findOrFail($id);
        $this->editingId = $id;
        $this->formName = $dept->name;
        $this->formDescription = $dept->description;
        $this->formType = $dept->type;
        $this->formHeadName = $dept->head_name;
        $this->showForm = true;
    }

    public function save(SaveDepartmentAction $action): void
    {
        $this->validate();

        $action->execute(
            $this->organization,
            SaveDepartmentData::fromArray([
                'name' => $this->formName,
                'description' => $this->formDescription,
                'type' => $this->formType,
                'head_name' => $this->formHeadName,
                'existing_id' => $this->editingId,
            ])
        );

        session()->flash('dept_success', $this->editingId ? 'Department updated.' : 'Department created.');
        $this->resetForm();
        unset($this->departments);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
    }

    public function deleteDepartment(): void
    {
        app(DeleteDepartmentAction::class)->execute($this->organization, DeleteDepartmentData::fromId($this->deletingId));
        $this->deletingId = null;
        unset($this->departments);
        session()->flash('dept_success', 'Department deleted.');
    }

    private function resetForm(): void
    {
        $this->formName = '';
        $this->formDescription = null;
        $this->formType = null;
        $this->formHeadName = null;
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        return view('livewire.organizations.department-manager');
    }
}
