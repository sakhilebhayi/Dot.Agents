<?php

namespace App\Services\Workflows;

/** What a step promised to produce, checked by the platform before the next step relies on it. */
final class ResultCheck
{
    /** @param  array<string, mixed>|null  $expect */
    public static function problem(?array $expect, string $output): ?string
    {
        if (blank($expect)) {
            return null;
        }

        $data = json_decode($output, true);

        if (($expect['format'] ?? null) === 'json' && ! is_array($data)) {
            return 'The result was not structured data.';
        }

        foreach ((array) ($expect['keys'] ?? []) as $key) {
            if (! is_array($data) || ! array_key_exists($key, $data)) {
                return "The result is missing {$key}.";
            }
        }

        if (filled($expect['contains'] ?? null) && ! str_contains(mb_strtolower($output), mb_strtolower($expect['contains']))) {
            return "The result does not mention {$expect['contains']}.";
        }

        return null;
    }
}
