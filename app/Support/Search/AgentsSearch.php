<?php

namespace App\Support\Search;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\AgentSkill;
use App\Models\AgentWorkflow;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * The header search (R-52). It looks across the business the person has open, and nothing else: agents, runs, workflows, approvals and the
 * business's own custom tools. Every query names the business explicitly on top of the tenant scope the models already apply, the person must
 * be a member of that business, and a group is left out when the person's role cannot open its pages (runs need `runs.view`).
 *
 * What it reads is the label a page already prints: names, roles, statuses, a run's one-line summary, an approval's title. It never selects or
 * matches a run's input, output or error text, an approval's proposed action, an agent's instructions or a tool's webhook address and headers,
 * so a search cannot become a way to read a payload or a secret.
 */
final class AgentsSearch
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 80;

    private const MAX_WORDS = 5;

    public static function isSearchable(string $q): bool
    {
        return mb_strlen(trim($q)) >= self::MIN_LENGTH;
    }

    /** @return list<string> lower-cased words, at most MAX_WORDS, each longer than one character unless it is the only one */
    public static function words(string $q): array
    {
        $q = mb_strtolower(mb_substr(trim($q), 0, self::MAX_LENGTH));
        $words = array_values(array_unique(array_filter(preg_split('/\s+/u', $q) ?: [], fn (string $w): bool => $w !== '')));

        return array_slice($words, 0, self::MAX_WORDS);
    }

    /**
     * @return array<string, list<SearchHit>> group label => hits, empty groups left out
     */
    public function search(User $user, string $q, int $perGroup = 4): array
    {
        $org = TenantContext::id();
        if ($org === null || ! self::isSearchable($q) || ! $user->organizations()->where('organizations.id', $org)->exists()) {
            return [];
        }

        $words = self::words($q);
        $limit = max(1, min($perGroup, 50));
        $groups = [];

        $groups['Agents'] = $this->agents($org, $words, $limit);

        if ($user->canInOrganization('runs.view', $org)) {
            $groups['Runs'] = $this->runs($org, $words, $limit);
        }

        $groups['Workflows'] = $this->workflows($org, $words, $limit);
        $groups['Approvals'] = $this->approvals($org, $words, $limit);
        $groups['Custom tools'] = $this->tools($org, $words, $limit);

        return array_filter($groups, fn (array $hits): bool => $hits !== []);
    }

    /** The pages worth jumping to when nothing is typed: the ones every role can open. */
    public function pages(User $user, int $limit = 6): array
    {
        $pages = [
            ['Command centre', route('dashboard')],
            ['Approvals', route('governance.approvals')],
            ['Runs', route('runs.index')],
            ['Agents', route('agents.deployments')],
            ['Workflows', route('workflows.index')],
            ['Templates', route('marketplace')],
        ];

        return array_map(fn (array $p): SearchHit => new SearchHit('Go to', $p[0], null, $p[1]), array_slice($pages, 0, $limit));
    }

    /** @param  list<string>  $words */
    private function agents(int $org, array $words, int $limit): array
    {
        $rows = $this->matching(AgentDeployment::query(), $words, ['name', 'alias', 'role'])
            ->where('organization_id', $org)
            ->orderBy('name')->limit($limit)
            ->get(['id', 'uuid', 'name', 'role', 'status']);

        return $rows->map(fn (AgentDeployment $a): SearchHit => new SearchHit('Agents', $a->name, $a->role ?: null, route('agents.show', $a), $a->status))->all();
    }

    /** @param  list<string>  $words */
    private function runs(int $org, array $words, int $limit): array
    {
        $query = AgentRun::query()->where('organization_id', $org)->where('is_test', false);
        $query->where(function (Builder $outer) use ($words): void {
            foreach ($words as $word) {
                $like = self::like($word);
                $outer->where(function (Builder $w) use ($like): void {
                    $w->whereRaw("lower(summary) like ? escape '!'", [$like])
                        ->orWhereHas('deployment', fn (Builder $d) => $d->whereRaw("lower(name) like ? escape '!'", [$like]));
                });
            }
        });

        $rows = $query->with('deployment:id,name')->latest('id')->limit($limit)->get(['id', 'uuid', 'organization_id', 'agent_deployment_id', 'summary', 'status', 'created_at']);

        return $rows->map(fn (AgentRun $r): SearchHit => new SearchHit('Runs', $r->summary ?: 'Run', $r->deployment?->name, route('runs.show', $r), str_replace('_', ' ', (string) $r->status)))->all();
    }

    /** @param  list<string>  $words */
    private function workflows(int $org, array $words, int $limit): array
    {
        $rows = $this->matching(AgentWorkflow::query(), $words, ['name', 'description'])
            ->where('organization_id', $org)
            ->orderBy('name')->limit($limit)
            ->get(['id', 'uuid', 'name', 'status']);

        return $rows->map(fn (AgentWorkflow $w): SearchHit => new SearchHit('Workflows', $w->name, null, route('workflows.builder', $w), $w->status))->all();
    }

    /** @param  list<string>  $words */
    private function approvals(int $org, array $words, int $limit): array
    {
        $query = AgentApproval::query()->where('organization_id', $org);
        $query->where(function (Builder $outer) use ($words): void {
            foreach ($words as $word) {
                $like = self::like($word);
                $outer->where(function (Builder $w) use ($like): void {
                    $w->whereRaw("lower(title) like ? escape '!'", [$like])
                        ->orWhereHas('deployment', fn (Builder $d) => $d->whereRaw("lower(name) like ? escape '!'", [$like]));
                });
            }
        });

        $rows = $query->with('deployment:id,name')->latest('id')->limit($limit)->get(['id', 'uuid', 'organization_id', 'agent_deployment_id', 'title', 'status', 'risk_level']);

        return $rows->map(fn (AgentApproval $a): SearchHit => new SearchHit('Approvals', $a->title ?: 'Approval', $a->deployment?->name, route('governance.approvals', ['approval' => $a->uuid]), $a->status === 'pending' ? 'waiting' : $a->status))->all();
    }

    /** @param  list<string>  $words */
    private function tools(int $org, array $words, int $limit): array
    {
        // Only this business's own tools. The shared catalogue is not a team record, and webhook_url / webhook_headers are never selected.
        $rows = $this->matching(AgentSkill::query(), $words, ['name', 'description'])
            ->where('organization_id', $org)
            ->orderBy('name')->limit($limit)
            ->get(['id', 'organization_id', 'name', 'is_active']);

        return $rows->map(fn (AgentSkill $s): SearchHit => new SearchHit('Custom tools', $s->name, null, route('org.skills'), $s->is_active ? null : 'off'))->all();
    }

    /**
     * Every word must appear in one of the columns (case-insensitive, `%` and `_` typed by a person are literal).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $words
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    private function matching(Builder $query, array $words, array $columns): Builder
    {
        return $query->where(function (Builder $outer) use ($words, $columns): void {
            foreach ($words as $word) {
                $like = self::like($word);
                $outer->where(function (Builder $w) use ($columns, $like): void {
                    foreach ($columns as $column) {
                        $w->orWhereRaw('lower('.$column.") like ? escape '!'", [$like]);
                    }
                });
            }
        });
    }

    private static function like(string $word): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($word)).'%';
    }
}
