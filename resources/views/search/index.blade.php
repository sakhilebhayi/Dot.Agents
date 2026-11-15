<x-layouts.platform>
    <x-slot:header>Search</x-slot:header>
    <div class="page narrow search-page">
        <div class="page-head">
            <div>
                <h1>Search</h1>
                <p>
                    @if ($typed)
                        Results for &ldquo;{{ $q }}&rdquo; in this business: its agents, runs, workflows, approvals and custom tools.
                    @else
                        Search this business's agents, runs, workflows, approvals and custom tools. Type at least {{ \App\Support\Search\AgentsSearch::MIN_LENGTH }} characters in the search above.
                    @endif
                </p>
            </div>
        </div>

        @if (! $typed)
            <section class="card" aria-labelledby="sr-go">
                <div class="card-head"><h2 id="sr-go">Go to</h2></div>
                <div class="card-body search-chips">
                    @foreach ($pages as $page)
                        <a class="btn" href="{{ $page->url }}">{{ $page->title }}</a>
                    @endforeach
                </div>
            </section>
        @elseif ($groups === [])
            <div class="notice" role="status">Nothing matches &ldquo;{{ $q }}&rdquo;. Try fewer words, an agent name or part of a run summary.</div>
        @else
            <div class="grid">
                @foreach ($groups as $label => $hits)
                    <section class="card" aria-labelledby="sr-{{ \Illuminate\Support\Str::slug($label) }}">
                        <div class="card-head">
                            <h2 id="sr-{{ \Illuminate\Support\Str::slug($label) }}">{{ $label }}</h2>
                            <span class="chip">{{ count($hits) }}{{ count($hits) >= 25 ? '+' : '' }}</span>
                        </div>
                        @foreach ($hits as $hit)
                            <a class="row" href="{{ $hit->url }}">
                                <span class="row-main">
                                    <b>{{ \App\Support\Search\Highlight::mark($hit->title, $words) }}</b>
                                    @if ($hit->sub)<span>{{ $hit->sub }}</span>@endif
                                </span>
                                @if ($hit->tag)<span class="row-end"><span class="chip">{{ $hit->tag }}</span></span>@endif
                            </a>
                        @endforeach
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.platform>
