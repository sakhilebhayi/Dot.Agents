<?php

namespace App\Services\Agents;

use App\Models\AgentConfigVersion;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentTestResult;
use App\Models\AgentWorkflow;
use App\Models\KnowledgeBase;
use App\Services\Models\ModelCatalog;
use App\Services\Tools\ToolCatalogue;
use App\Services\Tools\ToolDefinition;
use Illuminate\Support\Str;

/**
 * Reads an agent's setup the way a careful colleague would, by rules that can be checked. A setup with no findings is
 * still not "ready": only a passing test of this exact version can say that.
 */
class AgentConfigReview
{
    public const BLOCKER = 'blocker';

    public const WARNING = 'warning';

    private const VAGUE = ['help', 'assist', 'support', 'everything', 'anything', 'various', 'stuff', 'things'];

    private const CONTROLLED = 4;

    public function __construct(private readonly ToolCatalogue $catalogue, private readonly ModelCatalog $models) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array{findings: list<array{level: string, title: string, fix: string}>, verdict: string, words: string}
     */
    public function review(AgentDeployment $agent, array $config, ?AgentConfigVersion $version = null): array
    {
        $tools = $this->tools($agent, $config);

        $findings = array_values(array_filter([
            ...$this->instructions($config),
            ...$this->responsibility($agent),
            ...$this->permissions($config, $tools),
            ...$this->knowledge($config),
            ...$this->unsupported($agent, $config),
            ...$this->conflicts($config, $tools),
            ...$this->execution($agent, $config),
        ]));

        array_push($findings, ...$this->tests($agent, $version));

        return [...$this->verdict($findings, $version), 'findings' => $findings];
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function tests(AgentDeployment $agent, ?AgentConfigVersion $version): array
    {
        if ($version === null) {
            return [];
        }

        $failing = AgentTestResult::where('config_version_id', $version->id)->whereIn('id', AgentTestResult::selectRaw('max(id)')->groupBy('agent_test_case_id', 'config_version_id'))->where('status', AgentTestResult::FAILED)->count();

        $findings = $failing > 0 ? [$this->warning("{$failing} saved ".Str::plural('test', $failing).' failed on this version', 'See what went wrong on the test page.')] : [];

        $live = AgentWorkflow::where('organization_id', $agent->organization_id)->where('status', 'active')
            ->whereHas('nodes', fn ($node) => $node->where('config->deployment_id', $agent->id))->count();

        if ($live > 0 && $version->state === AgentConfigVersion::DRAFT) {
            $findings[] = $this->warning('Live workflows use this agent', "Publishing changes the work of {$live} ".Str::plural('workflow', $live).'. Test it first.');
        }

        return $findings;
    }

    /** @param  array<string, mixed>  $config */
    public function hasBlockers(AgentDeployment $agent, array $config): bool
    {
        return collect($this->review($agent, $config)['findings'])->contains('level', self::BLOCKER);
    }

    /**
     * @param  list<array{level: string, title: string, fix: string}>  $findings
     * @return array{verdict: string, words: string}
     */
    private function verdict(array $findings, ?AgentConfigVersion $version): array
    {
        if (collect($findings)->contains('level', self::BLOCKER)) {
            return ['verdict' => 'not_ready', 'words' => 'Not ready. Fix what is marked below.'];
        }

        $tested = $version !== null && AgentRun::where('config_version_id', $version->id)->where('is_test', true)->where('status', AgentRun::COMPLETED)->exists()
            && ! collect($findings)->contains(fn ($finding) => str_contains($finding['title'], 'failed on this version'));

        return $tested
            ? ['verdict' => 'ready', 'words' => 'This version passed a test run and the checks below.']
            : ['verdict' => 'untested', 'words' => 'Nothing is blocking this, but it has not been tested. A clean setup does not prove the agent works.'];
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function instructions(array $config): array
    {
        $length = mb_strlen(trim((string) $config['instructions']));

        return match (true) {
            $length === 0 => [$this->blocker('It has no instructions', 'Say what it should do, step by step.')],
            $length < 40 => [$this->warning('The instructions are very short', 'Say how it should do the work and when it should stop and ask.')],
            default => [],
        };
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function responsibility(AgentDeployment $agent): array
    {
        $text = mb_strtolower(trim((string) $agent->responsibility));
        $words = str_word_count($text);

        if ($text === '') {
            return [$this->blocker('Nobody can say what it is responsible for', 'Write its responsibility in one sentence.')];
        }

        $vague = $words < 5 || ($words < 10 && array_intersect(self::VAGUE, explode(' ', $text)) !== []);

        return $vague ? [$this->warning('Its responsibility is vague', 'Name the work, for example "check supplier invoices against our terms".')] : [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, ToolDefinition>  $tools
     * @return list<array{level: string, title: string, fix: string}>
     */
    private function permissions(array $config, array $tools): array
    {
        $findings = [];
        $alone = collect($config['tools'])->where('mode', 'auto')->pluck('key')->intersect(collect($tools)->filter(fn ($tool) => $tool->writesState)->keys());

        if ($alone->isNotEmpty() && (int) $config['autonomy'] === self::CONTROLLED) {
            $findings[] = $this->warning('It can change things without asking', 'Set these to ask first unless you are sure: '.$alone->implode(', ').'.');
        }

        if (count($tools) > 8) {
            $findings[] = $this->warning('It has more tools than most jobs need', 'Keep only the tools this job uses.');
        }

        $sensitive = collect($tools)->contains(fn ($tool) => $tool->readsSensitive) || $config['knowledge'] !== [];
        $outward = collect($tools)->contains(fn ($tool) => $tool->externalReach);

        if ($sensitive && $outward) {
            $findings[] = $this->warning('It can read private information and also reach outside', 'That is how data leaks. The platform makes it ask a person once it has read outside content, but consider splitting the job into two agents.');
        }

        return $findings;
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function knowledge(array $config): array
    {
        $bases = KnowledgeBase::whereIn('id', $config['knowledge'])->withCount(['articles' => fn ($query) => $query->where('index_status', 'indexed')])->get();

        if ($config['knowledge'] === [] && preg_match('/\b(polic|handbook|document|knowledge|procedure|our terms|our rules)/i', (string) $config['instructions'])) {
            return [$this->warning('Its instructions mention documents, but it has none', 'Choose the knowledge it should read.')];
        }

        return $bases->filter(fn ($base) => $base->articles_count === 0)
            ->map(fn ($base) => $this->warning("{$base->name} has nothing ready to read", 'Add a document, or wait until it says Ready.'))->values()->all();
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function unsupported(AgentDeployment $agent, array $config): array
    {
        $known = $this->catalogue->all($agent->organization_id);

        return collect($config['tools'])->reject(fn ($grant) => isset($known[$grant['key']]))
            ->map(fn ($grant) => $this->blocker("{$grant['key']} is not a tool that exists here", 'Remove it, or connect it first.'))->values()->all();
    }

    /**
     * @param  array<string, ToolDefinition>  $tools
     * @return list<array{level: string, title: string, fix: string}>
     */
    private function conflicts(array $config, array $tools): array
    {
        $findings = [];
        $instructions = (string) $config['instructions'];

        preg_match_all('/\b(?:always)\s+(\w+)/i', $instructions, $always);
        preg_match_all('/\b(?:never)\s+(\w+)/i', $instructions, $never);

        if ($clash = array_intersect(array_map('strtolower', $always[1]), array_map('strtolower', $never[1]))) {
            $findings[] = $this->warning('Its instructions contradict each other', 'It is told both always and never to '.reset($clash).'.');
        }

        if ((int) $config['autonomy'] === 1 && collect($tools)->contains(fn ($tool) => $tool->writesState)) {
            $findings[] = $this->warning('It is set to Assist but has tools that change things', 'At Assist it can never use them. Raise its autonomy or remove them.');
        }

        if (preg_match('/\b(send|email|message|notify)\b/i', $instructions) && ! collect($tools)->contains(fn ($tool) => $tool->writesState || $tool->externalReach)) {
            $findings[] = $this->warning('It is told to send something but has no tool to send with', 'Give it a tool, or change the instruction to draft only.');
        }

        if (preg_match('/\bremember\b/i', $instructions) && ! $config['memory']['long_term']) {
            $findings[] = $this->warning('It is told to remember, but long-term memory is off', 'Turn on long-term memory, or change the instruction.');
        }

        return $findings;
    }

    /** @return list<array{level: string, title: string, fix: string}> */
    private function execution(AgentDeployment $agent, array $config): array
    {
        return array_values(array_filter([
            $agent->owner_id === null ? $this->warning('Nobody owns this agent', 'Choose who answers for it.') : null,
            (float) $config['limits']['cost'] > 20 ? $this->warning('Its spending limit per run is high', 'Most work costs far less. Lower it unless you know why it is high.') : null,
            (int) $config['limits']['steps'] > 40 ? $this->warning('It can take a very long time', 'A run that needs this many steps is usually going in circles.') : null,
            $this->models->isReady() ? null : $this->warning('No AI provider is set up on this platform', 'Runs will fail until one is, and will say so.'),
        ]));
    }

    /** @return array<string, ToolDefinition> */
    private function tools(AgentDeployment $agent, array $config): array
    {
        $known = $this->catalogue->all($agent->organization_id);
        $tools = [];

        foreach ($config['tools'] as $grant) {
            if (isset($known[$grant['key']])) {
                $tools[$grant['key']] = $known[$grant['key']]->definition();
            }
        }

        return $tools;
    }

    /** @return array{level: string, title: string, fix: string} */
    private function blocker(string $title, string $fix): array
    {
        return ['level' => self::BLOCKER, 'title' => $title, 'fix' => $fix];
    }

    /** @return array{level: string, title: string, fix: string} */
    private function warning(string $title, string $fix): array
    {
        return ['level' => self::WARNING, 'title' => $title, 'fix' => $fix];
    }
}
