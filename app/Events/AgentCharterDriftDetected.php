<?php

namespace App\Events;

use App\Models\AgentDeployment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a chartered agent's runtime behavior diverges from its charter
 * scope. Charters are prose today with no structured, machine-comparable
 * scope field, so this event has no automated detector behind it yet — it
 * exists so the audit trail and listener plumbing are real and ready for
 * whoever fires it (manually, or once charters gain structured scope data).
 */
class AgentCharterDriftDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly AgentDeployment $deployment,
        public readonly string $description,
    ) {}
}
