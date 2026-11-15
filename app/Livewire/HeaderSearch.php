<?php

namespace App\Livewire;

use App\Support\Search\AgentsSearch;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The header search (R-52): the shared dot-ui pill, fed by AgentsSearch. Typing runs a debounced Livewire request; Enter (or the arrow) submits
 * the same query to the full results page (route `search`). The component only ever holds the query string: the rows are recomputed for the
 * signed-in person on every render, so nothing another business owns can be carried in component state.
 */
class HeaderSearch extends Component
{
    /** Actions a read-only (viewer) role may still call: searching changes nothing. */
    public const READ_ONLY_ACTIONS = ['updatedQ'];

    public string $q = '';

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, AgentsSearch::MAX_LENGTH);
    }

    public function render(AgentsSearch $search): View
    {
        $user = auth()->user();
        $typed = AgentsSearch::isSearchable($this->q);

        return view('livewire.header-search', [
            'typed' => $typed,
            'words' => $typed ? AgentsSearch::words($this->q) : [],
            'groups' => $typed && $user ? $search->search($user, $this->q, 4) : [],
            'pages' => ! $typed && $user ? $search->pages($user, 6) : [],
        ]);
    }
}
