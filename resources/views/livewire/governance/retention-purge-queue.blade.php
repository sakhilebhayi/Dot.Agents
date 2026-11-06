<x-sig.page title="Retention purges" sub="Rows past their retention window. Nothing is deleted until a person approves it.">
    @if(session('success'))
        <div class="notice clear" role="status">{{ session('success') }}</div>
    @endif

    <section class="card" aria-label="Purges awaiting review">
        <div class="card-head">
            <h2>Awaiting review</h2>
            <span class="muted num">{{ number_format($this->pendingCount) }}</span>
        </div>

        @if($this->proposals->isEmpty())
            <div class="empty">No retention purges awaiting review.</div>
        @else
            <div class="filters">
                <label class="field filters-wide">
                    <span>Reviewer notes (optional)</span>
                    <input class="input" type="text" wire:model="reviewerNotes" placeholder="Saved with your next decision">
                </label>
            </div>

            @foreach($this->proposals as $proposal)
                <div class="row long" wire:key="proposal-{{ $proposal->id }}">
                    <div class="row-main">
                        <b>{{ class_basename($proposal->model_class) }}</b>
                        <span>{{ $proposal->retention_summary }}</span>
                    </div>
                    <span class="chip hold"><span class="num">{{ number_format($proposal->eligible_count) }}</span> {{ Str::plural('row', $proposal->eligible_count) }} eligible</span>
                    <div class="row-actions">
                        <button type="button" class="btn small danger" wire:click="approve({{ $proposal->id }})" wire:confirm="Permanently delete {{ $proposal->eligible_count }} {{ class_basename($proposal->model_class) }} row(s)?">Approve purge</button>
                        <button type="button" class="btn small" wire:click="reject({{ $proposal->id }})">Reject</button>
                    </div>
                </div>
            @endforeach
        @endif

        @if($this->proposals->hasPages())
            <div class="card-body">{{ $this->proposals->links() }}</div>
        @endif
    </section>
</x-sig.page>
