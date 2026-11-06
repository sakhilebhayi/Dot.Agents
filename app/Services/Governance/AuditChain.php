<?php

namespace App\Services\Governance;

use Illuminate\Support\Facades\DB;

/**
 * Links audit entries so a change or a removal shows. Entries are sealed in order by one scheduled process rather than
 * as they are written, so two writers can never fork a chain. An entry can sit unsealed for about a minute.
 */
class AuditChain
{
    private const BATCH = 2000;

    public function seal(): int
    {
        $rows = DB::table('audit_logs')->whereNull('entry_hash')->orderBy('id')->limit(self::BATCH)->get();
        $heads = [];

        foreach ($rows as $row) {
            $key = (string) $row->organization_id;
            $heads[$key] ??= DB::table('audit_logs')->where('organization_id', $row->organization_id)->whereNotNull('entry_hash')->orderByDesc('id')->value('entry_hash');

            $hash = $this->hash($row, $heads[$key]);

            DB::table('audit_logs')->where('id', $row->id)->update(['previous_hash' => $heads[$key], 'entry_hash' => $hash]);
            $heads[$key] = $hash;
        }

        return $rows->count();
    }

    /** @return list<string> What is wrong. Empty means every sealed entry is as it was. */
    public function verify(): array
    {
        $problems = [];
        $heads = [];

        foreach (DB::table('audit_logs')->whereNotNull('entry_hash')->orderBy('id')->cursor() as $row) {
            $key = (string) $row->organization_id;
            $head = $heads[$key] ?? null;

            if ($row->previous_hash !== $head) {
                $problems[] = "An entry before {$row->id} is missing or was changed.";
            }

            if (! hash_equals($row->entry_hash, $this->hash($row, $row->previous_hash))) {
                $problems[] = "Entry {$row->id} was changed after it was sealed.";
            }

            $heads[$key] = $row->entry_hash;
        }

        return $problems;
    }

    private function hash(object $row, ?string $previous): string
    {
        return hash('sha256', implode("\x1f", [
            $row->id, $row->organization_id, $row->user_id, $row->agent_deployment_id, $row->event, $row->description,
            $row->old_values, $row->new_values, $row->metadata, $row->created_at, $previous,
        ]));
    }
}
