<?php

namespace App\Services\Tools;

use RuntimeException;

/** A tool that has started work somewhere else. The run waits for it, holding no process. */
class ToolSuspended extends RuntimeException
{
    public function __construct(public readonly int $childRunId)
    {
        parent::__construct('Waiting for another agent.');
    }
}
