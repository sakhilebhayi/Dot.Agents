<?php

use App\Services\Tools\BuiltIn;

return [
    // The queue connection for agent steps: a database queue of its own, so a slow model call never holds up mail or
    // billing, with a retry window longer than any step.
    'queue_connection' => env('AGENTS_QUEUE_CONNECTION', 'agents'),
    'queue' => 'agent-steps',

    // A run starts straight after the web response, in that same PHP process, so nobody waits for the next cron minute.
    // The queue carries on from there. 0 turns this off.
    'kick_seconds' => (int) env('AGENTS_KICK_SECONDS', 25),

    'lease_seconds' => 200,

    // A run that has made no progress for this long is recovered by the sweeper.
    'stale_seconds' => 300,

    'model' => [
        'default' => env('AGENTS_DEFAULT_MODEL', 'claude-sonnet-5-5'),
        'timeout' => (int) env('AGENTS_MODEL_TIMEOUT', 120),
        'max_output_tokens' => (int) env('AGENTS_MAX_OUTPUT_TOKENS', 4096),
    ],

    // What people can choose. A model only appears when its provider has a key on this host.
    'models' => [
        'claude-opus-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Opus 5.5', 'good_for' => 'Hard judgement'],
        'claude-sonnet-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Sonnet 5.5', 'good_for' => 'Most work'],
        'claude-haiku-5-5' => ['provider' => 'anthropic', 'label' => 'Claude Haiku 5.5', 'good_for' => 'Fast, simple steps'],
        'gpt-4o' => ['provider' => 'openai', 'label' => 'GPT-4o', 'good_for' => 'Most work'],
        'gpt-4o-mini' => ['provider' => 'openai', 'label' => 'GPT-4o mini', 'good_for' => 'Fast, simple steps'],
        'gemini-1.5-pro' => ['provider' => 'gemini', 'label' => 'Gemini 1.5 Pro', 'good_for' => 'Long documents'],
    ],

    // A fallback only ever stays inside the same provider: moving a business's data to another provider is its decision,
    // never something a run does because of an outage.
    'fallbacks' => [
        'claude-opus-5-5' => 'claude-sonnet-5-5',
        'claude-sonnet-5-5' => 'claude-haiku-5-5',
        'gpt-4o' => 'gpt-4o-mini',
    ],

    // Everything an agent could be given. Being listed gives nothing: a tool runs only if a published version grants it.
    'tools' => [
        BuiltIn\Calculator::class,
        BuiltIn\ReadWebPage::class,
        BuiltIn\TeamDirectory::class,
        BuiltIn\NotifyMember::class,
        BuiltIn\KnowledgeSearch::class,
        BuiltIn\RememberFact::class,
    ],
];
