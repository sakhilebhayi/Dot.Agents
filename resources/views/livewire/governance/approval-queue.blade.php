<x-sig.page title="Approvals" sub="Work that has stopped and is waiting for a person.">
    <div class="tabs" role="tablist" aria-label="Approvals">
        <button type="button" class="tab" role="tab" wire:click="setTab('pending')" aria-selected="{{ $filterStatus === 'pending' ? 'true' : 'false' }}">
            Waiting <span class="num">{{ $this->pendingCount }}</span>
        </button>
        <button type="button" class="tab" role="tab" wire:click="setTab('answered')" aria-selected="{{ $filterStatus === 'answered' ? 'true' : 'false' }}">Answered</button>
        <button type="button" class="tab" role="tab" wire:click="setTab('rules')" aria-selected="{{ $filterStatus === 'rules' ? 'true' : 'false' }}">Rules</button>
    </div>

    @if(session('success'))
        <div class="notice clear" role="status">{{ session('success') }}</div>
    @endif

    @if($filterStatus === 'rules')
        <livewire:governance.approval-rules />
    @else
        <div class="desk">
            <section class="card" aria-label="Queue">
                <div class="card-head">
                    <h2>{{ $filterStatus === 'pending' ? 'Waiting' : 'Answered' }}</h2>
                    <select class="select desk-filter" aria-label="Risk" wire:model.live="filterRisk">
                        <option value="">Any risk</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>

                @forelse($this->approvals as $approval)
                    <button type="button" class="row desk-row" wire:click="selectApproval({{ $approval->id }})" wire:key="approval-{{ $approval->id }}" @if($selectedApproval?->id === $approval->id) aria-current="true" @endif>
                        <x-sig.lamp :state="$approval->status === 'pending' ? 'waiting_approval' : $approval->status" class="desk-lamp" />
                        <span class="row-main">
                            <b>{{ $approval->title }}</b>
                            <span>{{ $approval->deployment?->name }}, {{ $approval->created_at->diffForHumans() }}</span>
                        </span>
                        <x-approval.risk :level="$approval->risk_level" />
                    </button>
                @empty
                    <div class="empty">
                        <b>{{ $filterStatus === 'pending' ? 'Nothing is waiting.' : 'Nothing has been answered yet.' }}</b>
                        When an agent reaches something it may not do alone, it stops and asks here.
                    </div>
                @endforelse

                @if($this->approvals->hasPages())
                    <div class="card-body">{{ $this->approvals->links() }}</div>
                @endif
            </section>

            @if($selectedApproval)
                <x-approval.card :approval="$selectedApproval" :reviewers="$this->reviewers" :changing="$changing" />
            @else
                <div class="card desk-idle">
                    <div class="empty">Choose an item to read what the agent wants to do.</div>
                </div>
            @endif
        </div>
    @endif
</x-sig.page>
