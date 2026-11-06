<?php

namespace App\Contracts;

/** A tool that depends on another platform. It is offered only once that platform is really connected. */
interface NeedsConnection
{
    /** Why it cannot be used for this business, or null when it can. */
    public function missing(int $organizationId): ?string;
}
