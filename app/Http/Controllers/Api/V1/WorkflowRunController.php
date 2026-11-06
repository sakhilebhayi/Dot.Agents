<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workflows\StartWorkflowAction;
use App\DTOs\Workflows\StartWorkflowData;
use App\Http\Controllers\Controller;
use App\Models\AgentWorkflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkflowRunController extends Controller
{
    public function store(Request $request, AgentWorkflow $workflow, StartWorkflowAction $action): JsonResponse
    {
        $data = $request->validate(['input' => ['required', 'string', 'max:20000']]);

        abort_unless((int) $workflow->organization_id === (int) session('current_organization_id'), 404);

        try {
            $execution = $action->execute($workflow, new StartWorkflowData($data['input'], $request->user(), idempotencyKey: $request->header('Idempotency-Key')));
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->validator->errors()->first()], 409);
        }

        return response()->json($this->shape($execution), 202);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json($this->shape(WorkflowExecution::where('uuid', $uuid)->firstOrFail()));
    }

    /** @return array<string, mixed> */
    private function shape(WorkflowExecution $execution): array
    {
        return [
            'id' => $execution->uuid,
            'status' => $execution->status,
            'error' => $execution->error_message,
            'steps' => $execution->steps()->orderBy('id')->get()->map(fn (WorkflowStep $step) => ['name' => $step->label, 'status' => $step->status])->all(),
            'results' => $execution->output_data['results'] ?? [],
        ];
    }
}
