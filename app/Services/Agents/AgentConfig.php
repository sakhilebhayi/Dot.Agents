<?php

namespace App\Services\Agents;

/**
 * The shape of an agent's setup. Everything a run needs to know about an agent comes from one of these, frozen in a
 * numbered version, so a run can always say exactly what it was told to do.
 */
final class AgentConfig
{
    public const TONES = ['plain' => 'Plain and direct', 'formal' => 'Formal', 'friendly' => 'Warm and friendly'];

    public const OUTPUTS = ['text' => 'Plain text', 'markdown' => 'Formatted text', 'json' => 'Structured data'];

    public const ON_FAILURE = ['stop' => 'Stop and tell the owner', 'retry' => 'Try again, then stop', 'escalate' => 'Hand to a person'];

    public const AUTONOMY = [
        1 => ['Assist', 'Suggests what to do. Changes nothing outside this platform.'],
        2 => ['Prepare', 'Prepares work for a person to review and send.'],
        3 => ['Execute with approval', 'Acts after a person approves each action that matters.'],
        4 => ['Controlled autonomy', 'Carries out low-risk actions on its own, inside its limits.'],
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'purpose' => '',
            'instructions' => '',
            'tone' => 'plain',
            'language' => 'English',
            'model' => ['model' => null, 'fallback' => true],
            'knowledge' => [],
            'memory' => ['conversation' => true, 'long_term' => false, 'retention_days' => 90],
            'tools' => [],
            'limits' => [
                'steps' => 12,
                'tool_calls' => 20,
                'tokens' => 60000,
                'cost' => 1.00,
                'seconds' => 900,
                'retries' => 2,
                'concurrency' => 3,
            ],
            'errors' => ['on_failure' => 'stop'],
            'output' => ['format' => 'markdown'],
            'reporting' => ['on_failure' => true, 'on_approval' => true, 'on_completion' => false],
        ];
    }

    /**
     * Fills in what a stored config does not say and drops what is not part of the shape.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalise(array $config): array
    {
        $defaults = self::defaults();
        $clean = [];

        foreach ($defaults as $key => $value) {
            $given = $config[$key] ?? null;

            $clean[$key] = match (true) {
                $given === null => $value,
                is_array($value) && array_is_list($value) => is_array($given) ? array_values($given) : $value,
                is_array($value) => array_intersect_key(array_replace($value, is_array($given) ? $given : []), $value),
                default => $given,
            };
        }

        return $clean;
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'purpose' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'tone' => ['required', 'in:'.implode(',', array_keys(self::TONES))],
            'language' => ['required', 'string', 'max:40'],
            'model.model' => ['nullable', 'string', 'max:120'],
            'model.fallback' => ['boolean'],
            'knowledge' => ['array', 'max:20'],
            'knowledge.*' => ['integer'],
            'memory.conversation' => ['boolean'],
            'memory.long_term' => ['boolean'],
            'memory.retention_days' => ['integer', 'min:1', 'max:3650'],
            'tools' => ['array', 'max:50'],
            'tools.*.key' => ['required', 'string', 'max:120'],
            'tools.*.mode' => ['required', 'in:auto,approve'],
            'limits.steps' => ['required', 'integer', 'min:1', 'max:100'],
            'limits.tool_calls' => ['required', 'integer', 'min:0', 'max:200'],
            'limits.tokens' => ['required', 'integer', 'min:1000', 'max:2000000'],
            'limits.cost' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'limits.seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'limits.retries' => ['required', 'integer', 'min:0', 'max:5'],
            'limits.concurrency' => ['required', 'integer', 'min:1', 'max:20'],
            'errors.on_failure' => ['required', 'in:'.implode(',', array_keys(self::ON_FAILURE))],
            'output.format' => ['required', 'in:'.implode(',', array_keys(self::OUTPUTS))],
            'reporting.on_failure' => ['boolean'],
            'reporting.on_approval' => ['boolean'],
            'reporting.on_completion' => ['boolean'],
        ];
    }
}
