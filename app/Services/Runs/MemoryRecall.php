<?php

namespace App\Services\Runs;

use App\Models\AgentMemory;
use App\Models\AgentRun;
use App\Services\Knowledge\Terms;

/**
 * What a run may remember. Personal memory belongs to the person who started the run and goes to nobody else. Only
 * accepted memories are used, and they always arrive as marked data in the conversation.
 */
class MemoryRecall
{
    private const LIMIT = 8;

    /** @return array{text: string, ids: list<int>}|null */
    public function for(AgentRun $run): ?array
    {
        if (! $run->configVersion->settings()['memory']['long_term']) {
            return null;
        }

        $memories = AgentMemory::where('organization_id', $run->organization_id)
            ->where('status', AgentMemory::ACTIVE)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($query) use ($run) {
                $query->where('scope', AgentMemory::SCOPE_BUSINESS)
                    ->orWhere(fn ($agent) => $agent->where('scope', AgentMemory::SCOPE_AGENT)->where('agent_deployment_id', $run->agent_deployment_id))
                    ->orWhere(fn ($person) => $person->where('scope', AgentMemory::SCOPE_PERSON)->where('agent_deployment_id', $run->agent_deployment_id)->where('user_id', $run->initiated_by ?? 0));
            })
            ->latest('id')->limit(100)->get();

        $terms = Terms::of((string) $run->input);
        $chosen = $memories->sortByDesc(fn (AgentMemory $memory) => count(array_intersect($terms, Terms::of($memory->subject.' '.$memory->content, 40))))->take(self::LIMIT);

        if ($chosen->isEmpty()) {
            return null;
        }

        return [
            'text' => $chosen->map(fn (AgentMemory $memory) => "- {$memory->subject}: {$memory->content}")->implode("\n"),
            'ids' => $chosen->pluck('id')->all(),
        ];
    }
}
