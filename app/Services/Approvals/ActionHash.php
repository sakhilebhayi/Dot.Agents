<?php

namespace App\Services\Approvals;

/** Ties an approval to exactly one action. What runs has to hash to what was approved, or it does not run. */
final class ActionHash
{
    /** @param  array<string, mixed>  $arguments */
    public static function of(string $toolKey, int $version, array $arguments): string
    {
        return hash('sha256', $toolKey.'|'.$version.'|'.json_encode(self::sorted($arguments), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sorted(array $value): array
    {
        ksort($value);

        return array_map(fn ($item) => is_array($item) ? self::sorted($item) : $item, $value);
    }
}
