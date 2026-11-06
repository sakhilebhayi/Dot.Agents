<?php

namespace App\Services\Tools;

use RuntimeException;

/** A tool that did not work. The message is shown to the model and in the journal, so it never holds a secret. */
class ToolFailure extends RuntimeException
{
    public function __construct(string $message, public readonly bool $transient = false, public readonly bool $outcomeUnknown = false)
    {
        parent::__construct($message);
    }
}
