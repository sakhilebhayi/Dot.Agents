<?php

namespace App\Services\Tools;

final readonly class ToolOutput
{
    /** @param  list<array<string, mixed>>  $sources */
    public function __construct(public string $content, public bool $untrusted = false, public array $sources = []) {}
}
