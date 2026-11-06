<?php

namespace App\Services\Models;

/** One model turn. Nothing else in the platform talks to a provider. */
interface ModelGateway
{
    /** @throws ModelFailure */
    public function turn(ModelRequest $request): ModelReply;
}
