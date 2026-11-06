<x-sig.page title="Memory" sub="What agents have kept from earlier work. Nothing here reaches an agent until it is in use." narrow>
    <div class="tabs" role="tablist" aria-label="Memory">
        <button type="button" class="tab" role="tab" wire:click="show('proposed')" aria-selected="{{ $status === 'proposed' ? 'true' : 'false' }}">To review <span class="num">{{ $this->proposedCount }}</span></button>
        <button type="button" class="tab" role="tab" wire:click="show('active')" aria-selected="{{ $status === 'active' ? 'true' : 'false' }}">In use</button>
    </div>

    <section class="card" aria-label="Memories">
        @forelse($this->memories as $memory)
            <div class="memory" wire:key="memory-{{ $memory->id }}">
                <div class="memory-head">
                    <b>{{ $memory->subject ?: 'Untitled' }}</b>
                    <span class="chip">{{ ['person' => 'One person', 'agent' => 'This agent', 'business' => 'Whole business'][$memory->scope] ?? $memory->scope }}</span>
                </div>

                @if($editing === $memory->id)
                    <form wire:submit="saveCorrection">
                        <textarea class="textarea" rows="3" aria-label="Correct this memory" wire:model="correction"></textarea>
                        @error('correction')<p class="error">{{ $message }}</p>@enderror
                        <div class="answer-main memory-actions">
                            <button type="submit" class="btn primary small">Save</button>
                            <button type="button" class="btn quiet small" wire:click="cancel">Cancel</button>
                        </div>
                    </form>
                @else
                    <p class="memory-text">{{ $memory->content }}</p>
                    <p class="muted memory-source">
                        {{ $memory->deployment?->name }}. {{ $memory->source ?? 'Source not recorded' }}@if($memory->expires_at), kept until {{ $memory->expires_at->format('j M Y') }}@endif
                    </p>
                    @if(auth()->user()->canInOrganization('memory.govern', $memory->organization_id))
                        <div class="answer-main memory-actions">
                            @if($status === 'proposed')
                                <button type="button" class="btn small" wire:click="accept({{ $memory->id }})">Use this</button>
                            @endif
                            <button type="button" class="btn quiet small" wire:click="edit({{ $memory->id }})">Correct</button>
                            <button type="button" class="btn quiet small" wire:click="remove({{ $memory->id }})" wire:confirm="Remove this memory for good?">Remove</button>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <div class="empty">
                <b>{{ $status === 'proposed' ? 'Nothing to review.' : 'Nothing is in use yet.' }}</b>
                Agents save facts here only when their setup allows it.
            </div>
        @endforelse

        @if($this->memories->hasPages())
            <div class="card-body">{{ $this->memories->links() }}</div>
        @endif
    </section>
</x-sig.page>
