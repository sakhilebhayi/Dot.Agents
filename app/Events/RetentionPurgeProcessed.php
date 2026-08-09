<?php

namespace App\Events;

use App\Models\RetentionPurgeProposal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RetentionPurgeProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RetentionPurgeProposal $proposal
    ) {}
}
