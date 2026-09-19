<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentSession;
use App\Models\AgentTask;

/**
 * Builds system prompts and task prompts for agent inference.
 *
 * Extracted from AgentOrchestrationService to keep it under 200 lines
 * and give prompt construction a single, testable home.
 */
class PromptBuilderService
{
    /**
     * Build the full system prompt for a chat session, incorporating
     * the deployment's persona, memory context, and custom instructions.
     */
    public function buildSystemPrompt(
        AgentDeployment $deployment,
        array $memoryContext = [],
        array $context = []
    ): string {
        $agent = $deployment->agent;
        $persona = $agent->defaultPersona;

        $basePrompt = $persona->system_prompt ?? $this->buildDefaultSystemPrompt($deployment);

        if (! empty($memoryContext)) {
            $memorySection = "\n\n## Relevant Memory Context\n".implode("\n", array_map(
                fn ($m) => "- [{$m['type']}] {$m['content']}",
                $memoryContext
            ));
            $basePrompt .= $memorySection;
        }

        if (! empty($deployment->custom_instructions)) {
            $basePrompt .= "\n\n## Organization-Specific Instructions\n".$deployment->custom_instructions;
        }

        return $basePrompt;
    }

    /**
     * Build the default system prompt for a deployment that has no persona configured.
     */
    public function buildDefaultSystemPrompt(AgentDeployment $deployment): string
    {
        $agent = $deployment->agent;
        $org = $deployment->organization;

        return "You are {$agent->name}, an AI agent deployed at {$org->name}. "
            ."Your role: {$agent->description}. "
            ."Deployment mode: {$deployment->deployment_mode}. "
            .'Always be precise, honest about uncertainty, and flag when you need human review. '
            .'Never fabricate data, statistics, or references. '
            .'If confidence is below 75%, explicitly state uncertainty and recommend verification.';
    }

    /**
     * Build the conversation history array from an active session.
     * Limited to the most recent 20 messages to keep context windows manageable.
     */
    public function buildConversationHistory(AgentSession $session): array
    {
        return $session->messages()
            ->orderBy('created_at')
            ->take(20)
            ->get()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();
    }

    /**
     * Build a structured task execution prompt for the agent.
     */
    public function buildTaskPrompt(AgentDeployment $deployment, AgentTask $task): string
    {
        $prompt = "## Task Assignment\n\n";
        $prompt .= "**Title:** {$task->title}\n\n";
        $prompt .= "**Description:** {$task->description}\n\n";

        if (! empty($task->input_data)) {
            $prompt .= "**Input Data:**\n```json\n".json_encode($task->input_data, JSON_PRETTY_PRINT)."\n```\n\n";
        }

        $prompt .= "## Required Output Format\n\n";
        $prompt .= "Respond with a JSON object containing:\n";
        $prompt .= "- `summary`: Brief summary of findings/actions (string)\n";
        $prompt .= "- `result`: Main output/results (object)\n";
        $prompt .= "- `confidence`: Your confidence score 0-100 (number)\n";
        $prompt .= "- `reasoning`: Step-by-step reasoning (string)\n";
        $prompt .= "- `evidence`: Data/sources used (array)\n";
        $prompt .= "- `assumptions`: Any assumptions made (array)\n";
        $prompt .= "- `risks`: Identified risks (array)\n";
        $prompt .= "- `recommendations`: Next steps (array)\n";
        $prompt .= "- `impact_score`: Estimated business impact 0-100 (number)\n\n";
        $prompt .= 'Be thorough, accurate, and transparent about any limitations or uncertainties.';

        return $prompt;
    }
}
