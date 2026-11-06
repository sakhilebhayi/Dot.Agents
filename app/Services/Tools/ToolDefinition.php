<?php

namespace App\Services\Tools;

/**
 * What a tool is, in the terms the policy gate decides on. These flags come from the code or from the person who set
 * the tool up, never from a model.
 */
final readonly class ToolDefinition
{
    /** Things no setting can let an agent do alone. */
    public const CEILING_MONEY = 'moving money';

    public const CEILING_PERSON = 'a final decision about a person';

    public const CEILING_DELETE = 'deleting something that cannot be recovered';

    /**
     * @param  array<string, mixed>  $schema  JSON Schema for the arguments
     * @param  bool  $untrustedOutput  what comes back could have been written by anyone
     * @param  bool  $safeToRepeat  running it twice does no harm, so a lost answer can simply be asked for again
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $category,
        public string $description,
        public string $accesses,
        public array $schema,
        public int $version = 1,
        public bool $readsSensitive = false,
        public bool $writesState = false,
        public bool $externalReach = false,
        public bool $untrustedOutput = false,
        public bool $safeToRepeat = true,
        public int $timeoutSeconds = 15,
        public int $retries = 0,
        public ?string $ceiling = null,
    ) {}
}
