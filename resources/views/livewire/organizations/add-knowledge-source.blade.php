<form class="card" wire:submit="save">
    <div class="card-head">
        <h2>Add a source</h2>
        <select class="select desk-filter" aria-label="Kind of source" wire:model.live="kind">
            <option value="upload">A file</option>
            <option value="website">A website</option>
        </select>
    </div>
    <div class="card-body rule-form">
        <label class="field">
            <span>Name</span>
            <input class="input" wire:model="title" maxlength="255">
            @error('title')<p class="error">{{ $message }}</p>@enderror
        </label>

        @if($kind === 'upload')
            <label class="field">
                <span>File</span>
                <input class="input" type="file" wire:model="file" accept=".pdf,.docx,.xlsx,.csv,.txt,.md">
                <small>PDF, Word, Excel, CSV or text, up to 20 MB. A scanned PDF has no text to read.</small>
                @error('file')<p class="error">{{ $message }}</p>@enderror
            </label>
        @else
            <label class="field">
                <span>Address</span>
                <input class="input" type="url" wire:model="url" placeholder="https://">
                <small>Read once now. Read it again from its row when the page changes.</small>
                @error('url')<p class="error">{{ $message }}</p>@enderror
            </label>
        @endif

        <button class="btn primary" type="submit" wire:loading.attr="disabled">Add source</button>
    </div>
</form>
