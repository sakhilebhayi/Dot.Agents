<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Contracts\NeedsConnection;
use App\Models\Organization;
use App\Services\Integrations\DotFilesClient;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolOutput;

/** Files a text document in the business's Dot.Files. Only offered once Dot.Files is connected for that business. */
class SaveToFiles implements AgentTool, NeedsConnection
{
    public function __construct(private readonly DotFilesClient $files) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'save_to_files',
            label: 'Save a document to Dot.Files',
            category: 'Files and documents',
            description: 'Saves a text document in this business\'s Dot.Files, in the folder for agents.',
            accesses: 'The folder for agents in this business\'s Dot.Files.',
            schema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'maxLength' => 150], 'text' => ['type' => 'string', 'maxLength' => 200000]], 'required' => ['name', 'text']],
            writesState: true,
            externalReach: true,
            safeToRepeat: false,
            timeoutSeconds: 20,
        );
    }

    public function missing(int $organizationId): ?string
    {
        return match (true) {
            ! $this->files->enabled() => 'Dot.Files is not connected to this platform yet.',
            blank(Organization::find($organizationId)?->infodot_team_uuid) => 'This business is not linked to its InfoDot company yet.',
            default => null,
        };
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $team = (string) Organization::findOrFail($context->organizationId())->infodot_team_uuid;
        $name = str_ends_with(strtolower($arguments['name']), '.txt') ? $arguments['name'] : "{$arguments['name']}.txt";

        $saved = $this->files->store($team, $name, $arguments['text'], $context->idempotencyKey);

        return new ToolOutput("Saved as {$name}. Reference: ".($saved['reference'] ?? $saved['uuid'] ?? 'not given'));
    }
}
