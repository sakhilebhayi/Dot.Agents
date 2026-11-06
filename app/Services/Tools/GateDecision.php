<?php

namespace App\Services\Tools;

final readonly class GateDecision
{
    public const ALLOW = 'allow';

    public const ASK = 'ask';

    public const DENY = 'deny';

    public function __construct(public string $verdict, public string $code, public string $reason) {}

    public static function allow(): self
    {
        return new self(self::ALLOW, 'allowed', '');
    }

    public static function ask(string $code, string $reason): self
    {
        return new self(self::ASK, $code, $reason);
    }

    public static function deny(string $code, string $reason): self
    {
        return new self(self::DENY, $code, $reason);
    }

    public function isDenied(): bool
    {
        return $this->verdict === self::DENY;
    }
}
