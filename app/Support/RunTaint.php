<?php

namespace App\Support;

use App\Models\AgentRun;

/** What a run has been exposed to. It only ever grows, and it is stored as bits on the run. */
final class RunTaint
{
    public const UNTRUSTED = 1;

    public const SENSITIVE = 2;

    public static function has(AgentRun $run, int $flag): bool
    {
        return ($run->taint & $flag) === $flag;
    }

    public static function add(AgentRun $run, int $flag): void
    {
        if (! self::has($run, $flag)) {
            $run->update(['taint' => $run->taint | $flag]);
        }
    }

    /** Words that arrive through a webhook or a message were written by someone outside the business. */
    public static function forTrigger(string $trigger): int
    {
        return in_array($trigger, ['webhook', 'event', 'email', 'social'], true) ? self::UNTRUSTED : 0;
    }
}
