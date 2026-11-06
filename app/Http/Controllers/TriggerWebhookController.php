<?php

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\AgentTrigger;
use App\Services\Triggers\TriggerFirer;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where other systems start work. Signed with the secret shown once, recent, and small. What arrives is outside content
 * from the first moment: the run starts tainted and it reaches the model only as marked data.
 */
class TriggerWebhookController extends Controller
{
    private const MAX_BYTES = 65536;

    private const MAX_AGE_SECONDS = 300;

    public function __invoke(Request $request, string $uuid, TriggerFirer $firer): JsonResponse
    {
        $trigger = TenantContext::system(fn () => AgentTrigger::where('uuid', $uuid)->where('kind', AgentTrigger::WEBHOOK)->first());

        abort_if($trigger === null || ! $trigger->is_active, 404);
        abort_if(strlen($request->getContent()) > self::MAX_BYTES, 413);
        abort_unless($this->signed($request, (string) $trigger->secret), 401);

        $key = (string) ($request->header('Idempotency-Key') ?: hash('sha256', $request->getContent()));

        $work = TenantContext::forOrganization($trigger->organization_id, fn () => $firer->fire($trigger, mb_substr($key, 0, 64), $request->getContent()));

        abort_if($work === null, 409, 'The target refused this work.');

        return response()->json(['accepted' => true, 'id' => $work->uuid, 'type' => $work instanceof AgentRun ? 'run' : 'workflow'], 202);
    }

    private function signed(Request $request, string $secret): bool
    {
        $stamp = (string) $request->header('X-Dot-Timestamp');
        $signature = (string) $request->header('X-Dot-Signature');

        if (! ctype_digit($stamp) || abs(now()->timestamp - (int) $stamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $stamp.'.'.$request->getContent(), $secret), $signature);
    }
}
