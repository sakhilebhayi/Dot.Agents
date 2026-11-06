<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agents\CancelRunAction;
use App\Actions\Agents\StartRunAction;
use App\DTOs\Agents\CancelRunData;
use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Http\Controllers\Controller;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Starting and following work from another system. Words that arrive here came from outside the business. */
class RunController extends Controller
{
    public function store(Request $request, AgentDeployment $deployment, StartRunAction $action): JsonResponse
    {
        Gate::authorize('view', $deployment);
        abort_unless($request->user()->canInOrganization('agents.operate', $deployment->organization_id), 403);

        $data = $request->validate(['input' => ['required', 'string', 'max:20000']]);

        try {
            $run = $action->execute($deployment, new StartRunData(
                input: $data['input'],
                triggerType: 'api',
                initiatedBy: $request->user()->id,
                idempotencyKey: $request->header('Idempotency-Key'),
                taint: 1,
            ));
        } catch (RunRefused $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json($this->shape($run), 202);
    }

    public function show(AgentRun $run): JsonResponse
    {
        Gate::authorize('view', $run);

        return response()->json($this->shape($run));
    }

    public function cancel(Request $request, AgentRun $run, CancelRunAction $action): JsonResponse
    {
        $action->execute($run, new CancelRunData($request->user()));

        return response()->json($this->shape($run->refresh()));
    }

    /** @return array<string, mixed> */
    private function shape(AgentRun $run): array
    {
        return [
            'id' => $run->uuid,
            'status' => $run->status,
            'output' => $run->status === AgentRun::COMPLETED ? $run->output : null,
            'error' => $run->error_message,
            'steps_used' => $run->steps_used,
            'tool_calls_used' => $run->tool_calls_used,
            'tokens_used' => $run->tokens_used,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }
}
