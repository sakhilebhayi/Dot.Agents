<?php

namespace App\Services\Agents;

use App\Models\UsageRecord;
use App\Services\Models\ModelCatalog;
use App\Services\Models\ModelFailure;
use App\Services\Models\ModelGateway;
use App\Services\Models\ModelRequest;
use App\Services\Models\ScriptedGateway;
use App\Services\Tools\ToolCatalogue;
use Illuminate\Support\Str;

/**
 * Turns a person's description into a proposed setup for them to check. With a model available it asks one; without, it
 * works from the words used, and the proposal says which it was. Either way nothing is created until they accept.
 */
class AgentDescriptionReader
{
    private const TOOL_WORDS = [
        'calculator' => '/\b(total|calculat|add up|figures?|sum|percent|invoice)/i',
        'web_read' => '/\b(website|web page|online|internet|research|url)/i',
        'team_directory' => '/\b(team|staff|employees?|colleagues?|who)/i',
        'notify_member' => '/\b(notify|tell|remind|alert|message|inform)/i',
    ];

    private const SYSTEM = <<<'TEXT'
    You draft a setup for a business agent from a description. Reply with one JSON object only, with these keys:
    name (short), role (a job title), responsibility (one sentence), purpose (one sentence), instructions (plain steps, including when to stop and ask),
    tools (a list chosen only from the tools given), autonomy (1 suggests only, 2 prepares for a person, 3 acts after approval). Never choose 4.
    TEXT;

    public function __construct(
        private readonly ModelGateway $gateway,
        private readonly ModelCatalog $models,
        private readonly ToolCatalogue $catalogue,
    ) {}

    /** @return array{name: string, role: string, responsibility: string, purpose: string, instructions: string, tools: list<string>, autonomy: int, by: string} */
    public function read(int $organizationId, string $description): array
    {
        if ($this->gateway instanceof ScriptedGateway || $this->models->isReady()) {
            return $this->byModel($organizationId, $description) ?? $this->byRules($description);
        }

        return $this->byRules($description);
    }

    /** @return array{name: string, role: string, responsibility: string, purpose: string, instructions: string, tools: list<string>, autonomy: int, by: string} */
    private function byRules(string $description): array
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $description));
        $tools = array_keys(array_filter(self::TOOL_WORDS, fn ($pattern) => preg_match($pattern, $text) === 1));
        $topic = $this->topic($text);

        return [
            'name' => Str::title($topic).' Agent',
            'role' => Str::title($topic).' assistant',
            'responsibility' => Str::limit($text, 200),
            'purpose' => '',
            'instructions' => "Your job: {$text}\nWork through it step by step. If something is missing or unclear, stop and ask rather than guessing.",
            'tools' => $tools,
            'autonomy' => in_array('notify_member', $tools, true) ? 3 : 2,
            'by' => 'rules',
        ];
    }

    /** @return array{name: string, role: string, responsibility: string, purpose: string, instructions: string, tools: list<string>, autonomy: int, by: string}|null */
    private function byModel(int $organizationId, string $description): ?array
    {
        $available = collect($this->catalogue->all($organizationId))->map(fn ($tool) => $tool->definition()->description);

        try {
            $reply = $this->gateway->turn(new ModelRequest(
                model: $this->models->resolve(null) ?? 'scripted',
                system: self::SYSTEM,
                messages: [['role' => 'user', 'text' => "Tools:\n".$available->map(fn ($text, $key) => "{$key}: {$text}")->implode("\n")."\n\nDescription:\n<data>{$description}</data>"]],
                tools: [],
                maxTokens: 1000,
            ));
        } catch (ModelFailure) {
            return null;
        }

        UsageRecord::create(['organization_id' => $organizationId, 'metric_type' => 'tokens', 'quantity' => $reply->inputTokens + $reply->outputTokens, 'model_used' => $reply->model, 'reference_type' => 'agent_draft', 'recorded_date' => now()->toDateString()]);

        $proposal = json_decode((string) Str::of($reply->text)->between('{', '}')->prepend('{')->append('}'), true);

        if (! is_array($proposal) || blank($proposal['name'] ?? null) || blank($proposal['instructions'] ?? null)) {
            return null;
        }

        return [
            'name' => Str::limit((string) $proposal['name'], 90, ''),
            'role' => Str::limit((string) ($proposal['role'] ?? ''), 110, ''),
            'responsibility' => Str::limit((string) ($proposal['responsibility'] ?? ''), 490, ''),
            'purpose' => Str::limit((string) ($proposal['purpose'] ?? ''), 490, ''),
            'instructions' => Str::limit((string) $proposal['instructions'], 8000, ''),
            'tools' => array_values(array_intersect((array) ($proposal['tools'] ?? []), $available->keys()->all())),
            'autonomy' => min(3, max(1, (int) ($proposal['autonomy'] ?? 2))),
            'by' => 'model',
        ];
    }

    private function topic(string $text): string
    {
        $words = array_diff(str_word_count(mb_strtolower($text), 1), ['create', 'build', 'make', 'agent', 'that', 'which', 'monitor', 'monitors', 'check', 'checks', 'review', 'reviews', 'read', 'reads', 'draft', 'drafts', 'write', 'writes', 'send', 'sends', 'our', 'the', 'and', 'for', 'with', 'from', 'incoming', 'new', 'all', 'every', 'each', 'want', 'need', 'please', 'an', 'a', 'to', 'of', 'in', 'on']);

        return implode(' ', array_slice(array_values($words), 0, 2)) ?: 'Business';
    }
}
