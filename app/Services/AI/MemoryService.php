<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentMemory;

class MemoryService
{
    /** Memories an old workflow step may read: accepted, and never one that belongs to a person. */
    public function getRelevantMemories(AgentDeployment $deployment, string $input, int $limit = 5): array
    {
        return $this->keywordSearch($deployment, $input, $limit);
    }

    public function keywordSearch(AgentDeployment $deployment, string $input, int $limit = 5): array
    {
        $memories = AgentMemory::where('agent_deployment_id', $deployment->id)
            ->active()
            ->where('status', AgentMemory::ACTIVE)
            ->where('scope', '!=', AgentMemory::SCOPE_PERSON)
            ->orderByDesc('importance_score')
            ->take($limit * 3)
            ->get();

        $keywords = $this->extractKeywords($input);

        $scored = $memories->map(function ($memory) use ($keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (stripos($memory->content, $kw) !== false) {
                    $score += 10;
                }
                if (stripos($memory->subject ?? '', $kw) !== false) {
                    $score += 5;
                }
            }

            return ['memory' => $memory, 'relevance' => $score];
        });

        return $scored
            ->sortByDesc('relevance')
            ->take($limit)
            ->filter(fn ($item) => $item['relevance'] > 0)
            ->map(fn ($item) => [
                'type' => $item['memory']->memory_type,
                'subject' => $item['memory']->subject,
                'content' => $item['memory']->content,
                'importance' => $item['memory']->importance_score,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Process an interaction and update memory.
     */
    public function processInteraction(
        AgentDeployment $deployment,
        string $userMessage,
        string $agentResponse
    ): void {
        // Store short-term memory of this interaction
        $this->store($deployment, [
            'memory_type' => 'short_term',
            'memory_category' => 'interaction',
            'subject' => 'Recent Conversation',
            'content' => "User: {$userMessage}\nAgent: ".substr($agentResponse, 0, 300),
            'importance_score' => 30,
            'expires_at' => now()->addHours(24),
        ]);

        // Clean up old short-term memories
        $this->pruneShortTermMemory($deployment);
    }

    /**
     * Store a lesson learned from a task outcome.
     */
    public function storeLessonLearned(AgentDeployment $deployment, string $lesson, array $context = []): AgentMemory
    {
        return $this->store($deployment, [
            'memory_type' => 'long_term',
            'memory_category' => 'lesson',
            'subject' => 'Lesson Learned',
            'content' => $lesson,
            'context' => $context,
            'importance_score' => 70,
        ]);
    }

    /**
     * Store organizational policy in memory.
     */
    public function storeOrgPolicy(AgentDeployment $deployment, string $policy, string $subject): AgentMemory
    {
        return $this->store($deployment, [
            'memory_type' => 'organizational',
            'memory_category' => 'policy',
            'subject' => $subject,
            'content' => $policy,
            'importance_score' => 90,
            'is_verified' => true,
        ]);
    }

    private function store(AgentDeployment $deployment, array $data): AgentMemory
    {
        $memory = AgentMemory::create(array_merge([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
        ], $data));

        return $memory;
    }

    private function pruneShortTermMemory(AgentDeployment $deployment): void
    {
        $cutoff = now()->subHours(24);

        AgentMemory::where('agent_deployment_id', $deployment->id)
            ->where('memory_type', 'short_term')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    private function extractKeywords(string $text): array
    {
        // Remove common stop words and extract meaningful keywords
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'is', 'are', 'was'];
        $words = str_word_count(strtolower($text), 1);

        return array_filter($words, fn ($w) => ! in_array($w, $stopWords) && strlen($w) > 3);
    }
}
