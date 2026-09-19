<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agents\DecommissionDeploymentAction;
use App\Actions\Agents\DeployAgentAction;
use App\Actions\Agents\PauseDeploymentAction;
use App\Actions\Agents\UpdateDeploymentAction;
use App\DTOs\Agents\DeployAgentData;
use App\DTOs\Agents\UpdateDeploymentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeployAgentRequest;
use App\Http\Requests\UpdateDeploymentRequest;
use App\Models\AgentDeployment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = session('current_organization_id');

        $deployments = AgentDeployment::with(['agent:id,name,avatar,slug'])
            ->where('organization_id', $orgId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('deployed_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($deployments);
    }

    public function store(DeployAgentRequest $request, DeployAgentAction $action): JsonResponse
    {
        $orgId = session('current_organization_id');
        if (! $orgId) {
            return response()->json(['error' => 'No active organization context.'], 403);
        }

        $data = DeployAgentData::fromArray(array_merge(
            $request->validated(),
            [
                'organization_id' => $orgId,
                'deployed_by' => $request->user()->id,
            ]
        ));

        $deployment = $action->execute($data);

        return response()->json($deployment->load('agent'), 201);
    }

    public function show(AgentDeployment $deployment): JsonResponse
    {
        $this->authorizeOrgAccess($deployment);

        return response()->json([
            'data' => $deployment->load(['agent', 'latestScorecard']),
        ]);
    }

    public function update(UpdateDeploymentRequest $request, AgentDeployment $deployment, UpdateDeploymentAction $action): JsonResponse
    {
        $this->authorizeOrgAccess($deployment);

        $updated = $action->execute($deployment, UpdateDeploymentData::fromArray($request->validated()));

        return response()->json($updated);
    }

    public function pause(AgentDeployment $deployment, PauseDeploymentAction $action): JsonResponse
    {
        $this->authorizeOrgAccess($deployment);

        $action->execute($deployment);

        return response()->json(['message' => 'Deployment paused.', 'status' => $deployment->fresh()->status]);
    }

    public function decommission(AgentDeployment $deployment, DecommissionDeploymentAction $action): JsonResponse
    {
        $this->authorizeOrgAccess($deployment);

        $action->execute($deployment);

        return response()->json(['message' => 'Deployment decommissioned.']);
    }

    private function authorizeOrgAccess(AgentDeployment $deployment): void
    {
        $orgId = session('current_organization_id');
        if (! $orgId || $deployment->organization_id !== (int) $orgId) {
            abort(403, 'Access denied.');
        }
    }
}
