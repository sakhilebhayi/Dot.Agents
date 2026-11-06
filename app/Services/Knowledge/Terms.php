<?php

namespace App\Services\Knowledge;

/** Reduces text to the words worth searching for. */
final class Terms
{
    private const STOP = ['the', 'and', 'for', 'are', 'was', 'were', 'but', 'not', 'with', 'this', 'that', 'from', 'have', 'has', 'had', 'what', 'when', 'where', 'which', 'who', 'how', 'why', 'can', 'our', 'you', 'your', 'they', 'them', 'will', 'would', 'should', 'could', 'about', 'there', 'their', 'die', 'van', 'het', 'nie', 'wat', 'ons'];

    /** @return list<string> */
    public static function of(string $text, int $limit = 10): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $words);

        return array_slice(array_values(array_diff(array_unique($words[0]), self::STOP)), 0, $limit);
    }
}
