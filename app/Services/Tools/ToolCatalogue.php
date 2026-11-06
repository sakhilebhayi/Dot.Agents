<?php

namespace App\Services\Tools;

use App\Contracts\AgentTool;
use App\Contracts\NeedsConnection;
use App\Models\CustomTool;

/** Every tool that exists for a business: the built-in ones and its own published API tools. */
class ToolCatalogue
{
    /** @return array<string, AgentTool> */
    public function all(int $organizationId): array
    {
        $tools = [];

        foreach (config('agents.tools') as $class) {
            $tool = app($class);
            $tools[$tool->definition()->key] = $tool;
        }

        foreach (CustomTool::current()->where('organization_id', $organizationId)->get() as $custom) {
            $tool = new CustomApiTool($custom);
            $tools[$tool->definition()->key] = $tool;
        }

        return $tools;
    }

    /** Why this tool cannot be used by this business yet, if it depends on something not connected. */
    public function missing(int $organizationId, string $key): ?string
    {
        $tool = $this->find($organizationId, $key);

        return $tool instanceof NeedsConnection ? $tool->missing($organizationId) : null;
    }

    public function find(int $organizationId, string $key): ?AgentTool
    {
        return $this->all($organizationId)[$key] ?? null;
    }
}
