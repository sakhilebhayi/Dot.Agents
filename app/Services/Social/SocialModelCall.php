<?php

namespace App\Services\Social;

use App\Services\Models\ModelCatalog;
use App\Services\Models\ModelFailure;
use App\Services\Models\ModelGateway;
use App\Services\Models\ModelRequest;
use App\Services\Models\ScriptedGateway;
use Illuminate\Support\Str;

/** One model call that answers in JSON, for the social pack. It goes through the same gateway as everything else. */
class SocialModelCall
{
    public function __construct(private readonly ModelGateway $gateway, private readonly ModelCatalog $models) {}

    /**
     * @param  list<array{role: string, text: string}>  $messages
     * @return array<string, mixed>
     *
     * @throws ModelFailure
     */
    public function json(string $system, array $messages, ?string $model = null, int $maxTokens = 800): array
    {
        $resolved = $this->gateway instanceof ScriptedGateway
            ? ($model ?? 'scripted')
            : ($this->models->resolve($model) ?? throw new ModelFailure('no_provider', 'No AI provider is set up on this platform yet.'));

        $reply = $this->gateway->turn(new ModelRequest($resolved, $system."\nReply with one JSON object and nothing else.", $messages, [], $maxTokens));

        return (array) json_decode((string) Str::of($reply->text)->between('{', '}')->prepend('{')->append('}'), true);
    }
}
