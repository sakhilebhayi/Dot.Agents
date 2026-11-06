<?php

namespace App\Services\Runs;

use App\Models\AgentRun;
use App\Models\AgentRunStep;
use App\Models\AgentUsageEvent;
use App\Models\ModelPrice;
use App\Models\UsageRecord;
use App\Services\Models\ModelReply;

/** The money ledger. Cost comes from a dated price a person entered, and with no price on file it stays unknown. */
class UsageLedger
{
    public function record(AgentRun $run, AgentRunStep $step, ModelReply $reply): AgentUsageEvent
    {
        $price = ModelPrice::where('provider', $reply->provider)->where('model', $reply->model)
            ->whereDate('effective_from', '<=', now())->orderByDesc('effective_from')->first();

        $cost = $price === null ? null : $this->cost($price, $reply);

        $event = AgentUsageEvent::create([
            'organization_id' => $run->organization_id,
            'agent_deployment_id' => $run->agent_deployment_id,
            'agent_run_id' => $run->id,
            'agent_run_step_id' => $step->id,
            'provider' => $reply->provider,
            'model' => $reply->model,
            'input_tokens' => $reply->inputTokens,
            'cached_tokens' => $reply->cachedTokens,
            'output_tokens' => $reply->outputTokens,
            'cost' => $cost,
            'model_price_id' => $price?->id,
        ]);

        // The plan's monthly token allowance and the billing pages read this table.
        UsageRecord::create([
            'organization_id' => $run->organization_id,
            'agent_deployment_id' => $run->agent_deployment_id,
            'metric_type' => 'tokens',
            'quantity' => $reply->inputTokens + $reply->outputTokens,
            'total_cost' => $cost ?? 0,
            'model_used' => $reply->model,
            'reference_type' => 'agent_run',
            'reference_id' => $run->id,
            'recorded_date' => now()->toDateString(),
        ]);

        $run->increment('tokens_used', $reply->inputTokens + $reply->outputTokens);

        if ($cost !== null) {
            $run->increment('cost', $cost);
        }

        return $event;
    }

    /** Spending that produced nothing, shown apart from useful spending. */
    public function markWasted(AgentRun $run): void
    {
        AgentUsageEvent::where('agent_run_id', $run->id)->update(['wasted' => true]);
        $run->update(['wasted_cost' => $run->cost]);
    }

    private function cost(ModelPrice $price, ModelReply $reply): float
    {
        $cached = min($reply->cachedTokens, $reply->inputTokens);
        $fresh = $reply->inputTokens - $cached;

        return ($fresh * (float) $price->input_per_million
            + $cached * (float) ($price->cache_read_per_million ?? $price->input_per_million)
            + $reply->outputTokens * (float) $price->output_per_million) / 1_000_000;
    }
}
