<?php

namespace App\Support;

/** Text from anywhere but the business's own setup. It stays marked as data and cannot close the marking itself. */
final class MarkedData
{
    public static function wrap(string $source, string $content): string
    {
        $safe = (string) preg_replace('/<(\/?)\s*data/i', '&lt;$1data', $content);

        return "<data source=\"{$source}\">\n{$safe}\n</data>";
    }
}
