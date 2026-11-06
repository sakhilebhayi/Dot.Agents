<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Models\Organization;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolOutput;

/** Who works here. Names and roles only, and always of the business the run belongs to. */
class TeamDirectory implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'team_directory',
            label: 'Look up the team',
            category: 'Business data',
            description: 'Lists the people in this business, with their id, name and role.',
            accesses: 'Names and roles of people in this business. Never email addresses.',
            schema: ['type' => 'object', 'properties' => (object) []],
            readsSensitive: true,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $members = Organization::findOrFail($context->organizationId())->users()->orderBy('name')->limit(200)->get()
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'role' => $user->getRelationValue('pivot')?->role]);

        return new ToolOutput((string) json_encode($members, JSON_UNESCAPED_UNICODE));
    }
}
