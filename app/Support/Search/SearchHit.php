<?php

namespace App\Support\Search;

/** One row of the header search: a stored record and the page that already shows it. */
final class SearchHit
{
    public function __construct(
        public readonly string $group,
        public readonly string $title,
        public readonly ?string $sub,
        public readonly string $url,
        /** a short word at the row's right edge (a status or a risk), never a payload */
        public readonly ?string $tag = null,
    ) {}
}
