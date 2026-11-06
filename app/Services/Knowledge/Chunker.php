<?php

namespace App\Services\Knowledge;

/** Splits a document into passages at paragraph ends, with a little overlap so a sentence is not lost at a join. */
class Chunker
{
    private const SIZE = 1400;

    private const OVERLAP = 150;

    /** @return list<string> */
    public function split(string $text): array
    {
        $chunks = [];
        $current = '';

        foreach (preg_split('/\n\s*\n|\n/u', $text) ?: [] as $paragraph) {
            foreach (mb_str_split(trim($paragraph), self::SIZE) ?: [] as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) > self::SIZE) {
                    $chunks[] = $current;
                    $current = mb_substr($current, -self::OVERLAP)."\n";
                }

                $current .= $piece."\n";
            }
        }

        return trim($current) === '' ? $chunks : [...$chunks, trim($current)];
    }
}
