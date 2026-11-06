<?php

namespace App\Contracts;

use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;

/** Something an agent can do. A tool says what it touches; whether it may run is decided by ToolGate, never by the tool. */
interface AgentTool
{
    public function definition(): ToolDefinition;

    /**
     * @param  array<string, mixed>  $arguments  already checked against the tool's schema
     *
     * @throws ToolFailure
     */
    public function run(ToolContext $context, array $arguments): ToolOutput;
}
