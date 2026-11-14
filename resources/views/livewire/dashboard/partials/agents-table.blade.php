<section class="sec" aria-label="Agents">
    <header class="sec-head"><h2>Agents<span class="n num">{{ count($this->roster) }}</span></h2><a class="btn quiet small" href="{{ route('agents.deployments') }}">All agents</a></header>
    @if($this->roster === [])
        <div class="empty-line">No agent has finished any work in this period.</div>
    @else
        <table class="roster">
            <thead><tr><th class="star-col"><span class="sr">Starred</span></th><th>Agent</th><th class="right hide-s">Runs</th><th class="right">Completed</th><th class="right">State</th></tr></thead>
            <tbody>
                @foreach($this->roster as $row)
                    <tr wire:key="agent-{{ $row['agent']->id }}">
                        <td class="star-col">
                            <button type="button" class="star" wire:click="toggleFavourite({{ $row['agent']->id }})" aria-pressed="{{ $row['starred'] ? 'true' : 'false' }}" aria-label="Star {{ $row['agent']->name }}">
                                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1.8l1.9 3.9 4.3.6-3.1 3 .7 4.3L8 11.6l-3.8 2 .7-4.3-3.1-3 4.3-.6z"/></svg>
                            </button>
                        </td>
                        <td class="name"><a href="{{ route('agents.show', $row['agent']) }}"><b>{{ $row['agent']->name }}</b><span>{{ $row['agent']->role }}</span></a></td>
                        <td class="right num hide-s">{{ $row['runs'] ?? '–' }}</td>
                        <td class="right num">{{ $row['rate'] === null ? '–' : $row['rate'].'%' }}</td>
                        <td class="right"><x-sig.lamp :state="$row['agent']->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
