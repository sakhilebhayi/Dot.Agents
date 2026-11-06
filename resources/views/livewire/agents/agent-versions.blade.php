<section class="card" aria-label="Versions">
    <div class="card-head"><h2>Versions</h2></div>
    @error('publish')<p class="card-body error" role="alert">That version has a problem that must be fixed first, so it was not restored.</p>@enderror
    @if(session('versions'))<p class="card-body muted" role="status">{{ session('versions') }}</p>@endif
    @forelse($this->versions as ['version' => $version, 'changed' => $changed])
        <div class="row" wire:key="version-{{ $version->id }}">
            <div class="row-main">
                <b>Version {{ $version->number }}</b>
                <span>{{ $changed === [] ? ($version->number === 1 ? 'First version' : 'No changes') : 'Changed '.implode(', ', $changed) }}</span>
            </div>
            <span class="chip {{ $version->state === 'published' ? 'clear' : '' }}">{{ ucfirst($version->state) }}</span>
            @if($version->state === 'retired')
                @can('update', $this->agent)
                    <button type="button" class="btn quiet small" wire:click="restore({{ $version->id }})" wire:confirm="Publish this setup again? New work will use it.">Restore</button>
                @endcan
            @endif
        </div>
    @empty
        <div class="empty">No versions yet.</div>
    @endforelse
</section>
