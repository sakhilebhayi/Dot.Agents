@props(['run'])

<ol class="timeline" aria-label="What happened">
    @foreach($run->steps()->orderBy('sequence')->get() as $step)
        @php
            $message = $step->payload['message'] ?? null;
            $words = $message['text'] ?? null;
            $calls = $message['calls'] ?? [];
            $results = $message['results'] ?? [];
        @endphp
        <li @class(['failed' => $step->status === 'failed', 'held' => $step->kind === 'approval'])>
            <i aria-hidden="true"></i>
            <div>
                <b>{{ $step->title }}</b>
                <span class="timeline-meta">
                    {{ $step->created_at->format('H:i:s') }}
                    @if($step->model) · {{ $step->model }} @endif
                    @if($step->tokens_in) · <span class="num">{{ number_format($step->tokens_in + $step->tokens_out) }}</span> tokens @endif
                    @if($step->attempt > 1) · attempt {{ $step->attempt }} @endif
                </span>
                @if($step->error_message)<p class="error">{{ $step->error_message }}</p>@endif
                @if(filled($step->payload['reason'] ?? null))<p class="muted">{{ $step->payload['reason'] }}</p>@endif
                @if(filled($words))<details><summary>What the model said</summary><pre>{{ $words }}</pre></details>@endif
                @foreach($calls as $call)
                    <details open><summary>Asked for {{ str_replace('_', ' ', $call['name']) }}</summary><pre>{{ json_encode($call['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>
                @endforeach
                @foreach($results as $result)
                    <details><summary>What came back</summary><pre>{{ $result['content'] }}</pre></details>
                @endforeach
                @foreach($step->payload['sources'] ?? [] as $source)
                    <p class="muted">Source: {{ $source['title'] }}, passage {{ $source['passage'] }}</p>
                @endforeach
            </div>
        </li>
    @endforeach
</ol>
