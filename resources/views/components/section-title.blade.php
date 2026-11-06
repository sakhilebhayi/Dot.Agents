<div class="card-head acct-head">
    <div>
        <h2>{{ $title }}</h2>
        <p class="muted">{{ $description }}</p>
    </div>

    @isset($aside)
        <div class="acct-aside">{{ $aside }}</div>
    @endisset
</div>
