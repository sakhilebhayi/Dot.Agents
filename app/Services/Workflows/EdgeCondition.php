<?php

namespace App\Services\Workflows;

use App\Models\WorkflowStep;

/**
 * Whether a line is taken. A condition is data a person chose, never code and never a model's judgement.
 * A line with no condition is taken when the step before it worked.
 */
final class EdgeCondition
{
    public const OPERATORS = ['always' => 'Always', 'failed' => 'If it failed', 'contains' => 'If the result contains', 'not_contains' => 'If the result does not contain', 'equals' => 'If the result is exactly'];

    public static function holds(mixed $condition, WorkflowStep $parent): bool
    {
        $condition = is_string($condition) ? json_decode($condition, true) : $condition;
        $operator = is_array($condition) ? ($condition['op'] ?? 'always') : 'always';

        if ($operator === 'failed') {
            return $parent->status === WorkflowStep::FAILED;
        }

        if ($parent->status !== WorkflowStep::COMPLETED) {
            return false;
        }

        $output = mb_strtolower((string) $parent->output);
        $value = mb_strtolower(trim((string) ($condition['value'] ?? '')));

        return match ($operator) {
            'contains' => $value !== '' && str_contains($output, $value),
            'not_contains' => $value === '' || ! str_contains($output, $value),
            'equals' => trim($output) === $value,
            default => true,
        };
    }
}
