<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Models\KnowledgeChunk;
use App\Services\Knowledge\KnowledgeRetriever;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolOutput;

/** Given to an agent by the knowledge its setup names. Passages come back with their source, so answers can say where. */
class KnowledgeSearch implements AgentTool
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'knowledge_search',
            label: 'Search business knowledge',
            category: 'Business data',
            description: 'Searches the documents this agent has been given, by the words in the question. Say which document an answer came from.',
            accesses: 'Only the knowledge sources chosen for this agent.',
            schema: ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'maxLength' => 500]], 'required' => ['query']],
            readsSensitive: true,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $chunks = $this->retriever->search($context->organizationId(), $this->retriever->basesFor($context->run), $arguments['query']);

        if ($chunks->isEmpty()) {
            return new ToolOutput('Nothing in the available documents matched. Say so rather than guessing.');
        }

        $sources = $chunks->map(fn (KnowledgeChunk $chunk) => ['article' => $chunk->article->uuid ?? $chunk->knowledge_article_id, 'title' => $chunk->article->title, 'passage' => $chunk->position + 1])->values()->all();
        $text = $chunks->map(fn (KnowledgeChunk $chunk) => "Source: {$chunk->article->title}, passage ".($chunk->position + 1)."\n{$chunk->content}")->implode("\n\n");

        // A document written outside the business could say anything, however it reached us.
        $outside = $chunks->contains(fn (KnowledgeChunk $chunk) => $chunk->article->source_type !== 'manual');

        return new ToolOutput($text, untrusted: $outside, sources: $sources);
    }
}
