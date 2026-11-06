<?php

namespace App\Services\Tools;

/**
 * Checks a model's arguments against a tool's schema. Only the parts of JSON Schema that tools here use, and it is
 * strict: an argument the schema does not name is refused, not ignored.
 */
final class ToolArguments
{
    private const MAX_STRING = 20000;

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $arguments
     * @return list<string> what is wrong, in words the model can act on
     */
    public function errors(array $schema, array $arguments): array
    {
        $errors = [];
        $properties = $schema['properties'] ?? [];

        foreach ($schema['required'] ?? [] as $name) {
            if (! array_key_exists($name, $arguments)) {
                $errors[] = "{$name} is required.";
            }
        }

        foreach ($arguments as $name => $value) {
            if (! isset($properties[$name])) {
                $errors[] = "{$name} is not an argument of this tool.";

                continue;
            }

            array_push($errors, ...$this->check((string) $name, $value, $properties[$name]));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string>
     */
    private function check(string $name, mixed $value, array $rule): array
    {
        $type = $rule['type'] ?? 'string';

        $fits = match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value),
            default => false,
        };

        if (! $fits) {
            return ["{$name} must be of type {$type}."];
        }

        return array_values(array_filter([
            isset($rule['enum']) && ! in_array($value, $rule['enum'], true) ? "{$name} must be one of: ".implode(', ', $rule['enum']).'.' : null,
            is_string($value) && mb_strlen($value) > ($rule['maxLength'] ?? self::MAX_STRING) ? "{$name} is too long." : null,
            is_numeric($value) && isset($rule['minimum']) && $value < $rule['minimum'] ? "{$name} is below the minimum." : null,
            is_numeric($value) && isset($rule['maximum']) && $value > $rule['maximum'] ? "{$name} is above the maximum." : null,
        ]));
    }
}
