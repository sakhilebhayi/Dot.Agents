<?php

declare(strict_types=1);

namespace App\DTOs\Organizations;

use Laravel\Sanctum\PersonalAccessToken;

readonly class RevokeApiTokenData
{
    public function __construct(
        public int $tokenId,
        public string $tokenName,
        public int $ownerId,
    ) {}

    public static function fromToken(PersonalAccessToken $token): self
    {
        return new self(
            tokenId: $token->id,
            tokenName: $token->name,
            ownerId: $token->tokenable_id,
        );
    }
}
