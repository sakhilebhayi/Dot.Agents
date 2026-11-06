<?php

namespace App\Services\Knowledge;

use App\Models\AgentRun;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Collection;

/**
 * Finds passages by the words in the question. It is keyword search, not semantic search: it finds what is written, not
 * what is meant. What it can look in comes from the run's pinned version, never from the model.
 */
class KnowledgeRetriever
{
    private const CANDIDATES = 300;

    /** @return list<int> The bases this run may read. */
    public function basesFor(AgentRun $run): array
    {
        $granted = array_map('intval', $run->configVersion->settings()['knowledge']);

        return KnowledgeBase::whereIn('id', $granted)->where('organization_id', $run->organization_id)->where('is_active', true)->get()
            ->filter(fn (KnowledgeBase $base) => $base->accessible_agents === null || $base->accessible_agents === [] || in_array($run->agent_deployment_id, $base->accessible_agents, true))
            ->pluck('id')->all();
    }

    /**
     * @param  list<int>  $bases
     * @return Collection<int, KnowledgeChunk>
     */
    public function search(int $organizationId, array $bases, string $question, int $limit = 5): Collection
    {
        $terms = Terms::of($question);

        if ($terms === [] || $bases === []) {
            return collect();
        }

        $candidates = KnowledgeChunk::with('article')
            ->where('organization_id', $organizationId)->whereIn('knowledge_base_id', $bases)
            ->whereHas('article', fn ($query) => $query->where('is_published', true))
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('search_text', 'like', "%{$term}%");
                }
            })
            ->limit(self::CANDIDATES)->get();

        return $this->ranked($candidates, $terms)->take($limit)->values();
    }

    /**
     * @param  Collection<int, KnowledgeChunk>  $chunks
     * @param  list<string>  $terms
     * @return Collection<int, KnowledgeChunk>
     */
    private function ranked(Collection $chunks, array $terms): Collection
    {
        $total = max(1, $chunks->count());
        $rarity = collect($terms)->mapWithKeys(fn ($term) => [$term => log(1 + $total / max(1, $chunks->filter(fn ($chunk) => str_contains($chunk->search_text, $term))->count()))]);

        return $chunks->sortByDesc(function (KnowledgeChunk $chunk) use ($rarity) {
            $words = max(50, str_word_count($chunk->search_text));

            return $rarity->sum(fn ($weight, $term) => $weight * log(1 + substr_count($chunk->search_text, $term)) / log($words));
        });
    }
}
