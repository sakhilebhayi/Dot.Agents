@php
    $canManage = auth()->user()->can('update', $this->organization);
    $types = ['business' => 'Business', 'technical' => 'Technical', 'operational' => 'Operational', 'operations' => 'Operations', 'support' => 'Support', 'finance' => 'Finance', 'hr' => 'HR'];
@endphp

<x-sig.page title="Departments" sub="Group your agents by the part of the business they work for." narrow>
    <x-slot:actions>
        @writable
            @if($canManage)
                <button type="button" class="btn primary" wire:click="openCreate"><x-sig.icon name="plus" />New department</button>
            @endif
        @endwritable
    </x-slot:actions>

    @if(session('dept_success'))
        <div class="notice clear" role="status">{{ session('dept_success') }}</div>
    @endif

    <div class="grid org-stack">
        @if($showForm)
            <form class="card" wire:submit="save">
                <div class="card-head"><h2>{{ $editingId ? 'Edit department' : 'New department' }}</h2></div>
                <div class="card-body">
                    <div class="pair-fields">
                        <label class="field">
                            <span>Name</span>
                            <input class="input" type="text" wire:model="formName" placeholder="Marketing" aria-required="true">
                            @error('formName')<p class="error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span>Type</span>
                            <select class="select" wire:model="formType">
                                <option value="">Choose a type</option>
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            <span>Department head</span>
                            <input class="input" type="text" wire:model="formHeadName" placeholder="Jane Smith">
                        </label>
                        <label class="field">
                            <span>Description</span>
                            <input class="input" type="text" wire:model="formDescription" placeholder="What this department does">
                        </label>
                    </div>
                    <div class="answer-main">
                        <button type="submit" class="btn primary" wire:loading.attr="disabled">{{ $editingId ? 'Save changes' : 'Create department' }}</button>
                        <button type="button" class="btn quiet" wire:click="$set('showForm', false)">Cancel</button>
                    </div>
                </div>
            </form>
        @endif

        <section class="card" aria-label="Departments">
            @forelse($this->departments as $dept)
                <div class="row template org-row" wire:key="department-{{ $dept->id }}">
                    <div class="row-main">
                        <b>{{ $dept->name }}</b>
                        @if($dept->description)<p class="muted">{{ $dept->description }}</p>@endif
                        <span>@if($dept->head_name)Led by {{ $dept->head_name }}, @endif<data class="num" value="{{ $dept->deployments_count }}">{{ $dept->deployments_count }}</data> {{ $dept->deployments_count === 1 ? 'agent' : 'agents' }}</span>
                    </div>
                    @if($dept->type)
                        <span class="chip">{{ $types[$dept->type] ?? ucfirst($dept->type) }}</span>
                    @endif
                    @writable
                        @if($canManage)
                            <div class="org-end">
                                <button type="button" class="btn quiet small" aria-label="Edit {{ $dept->name }}" wire:click="openEdit({{ $dept->id }})">Edit</button>
                                <button type="button" class="btn quiet small" aria-label="Delete {{ $dept->name }}" wire:click="confirmDelete({{ $dept->id }})">Delete</button>
                            </div>
                        @endif
                    @endwritable
                    @if($deletingId === $dept->id)
                        <div class="org-confirm" role="group" aria-label="Confirm deleting {{ $dept->name }}">
                            <p>Delete {{ $dept->name }}? Its agents stay, without a department.</p>
                            <div class="answer-main">
                                <button type="button" class="btn danger small" wire:click="deleteDepartment" wire:loading.attr="disabled">Delete</button>
                                <button type="button" class="btn quiet small" wire:click="$set('deletingId', null)">Cancel</button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty">
                    <b>No departments yet.</b>
                    Group agents here when the business has more than one kind of work.
                    @writable
                        @if($canManage)
                            <div><button type="button" class="btn" wire:click="openCreate">Create a department</button></div>
                        @endif
                    @endwritable
                </div>
            @endforelse
        </section>
    </div>
</x-sig.page>
