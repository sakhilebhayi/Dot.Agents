<?php

namespace App\Services\Models;

/** Which models this host can actually reach. */
final class ModelCatalog
{
    /** @return array<string, array{provider: string, label: string, good_for: string}> */
    public function available(): array
    {
        return array_filter(config('agents.models'), fn (array $model) => $this->providerReady($model['provider']));
    }

    public function providerFor(string $model): string
    {
        return config("agents.models.{$model}.provider") ?? match (true) {
            str_starts_with($model, 'claude') => 'anthropic',
            str_starts_with($model, 'gemini') => 'gemini',
            default => 'openai',
        };
    }

    public function providerReady(string $provider): bool
    {
        return filled(config("prism.providers.{$provider}.api_key"));
    }

    public function isReady(): bool
    {
        return $this->available() !== [];
    }

    /** The model to use: what was asked for if it can be reached, else the default, else the first that can. */
    public function resolve(?string $wanted): ?string
    {
        $available = $this->available();

        foreach ([$wanted, config('agents.model.default')] as $model) {
            if ($model !== null && isset($available[$model])) {
                return $model;
            }
        }

        return array_key_first($available);
    }

    /** The next model down in the same provider, or null. Fallbacks never cross providers. */
    public function fallbackFor(string $model): ?string
    {
        $next = config("agents.fallbacks.{$model}");

        return $next !== null && $this->providerFor($next) === $this->providerFor($model) && isset($this->available()[$next]) ? $next : null;
    }
}
