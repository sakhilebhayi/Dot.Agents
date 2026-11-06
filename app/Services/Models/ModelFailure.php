<?php

namespace App\Services\Models;

use RuntimeException;

/** A model call that did not produce an answer. Never stored as if it were one. */
class ModelFailure extends RuntimeException
{
    /** Worth trying again. */
    public const TRANSIENT = ['rate_limited', 'overloaded', 'timeout'];

    public function __construct(public readonly string $kind, string $message)
    {
        parent::__construct($message);
    }

    public function isTransient(): bool
    {
        return in_array($this->kind, self::TRANSIENT, true);
    }
}
