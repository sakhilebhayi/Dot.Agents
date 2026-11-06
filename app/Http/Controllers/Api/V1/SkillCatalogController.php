<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentSkill;
use App\Support\TaggableCache;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only catalog endpoints for browsing the available skill library.
 *
 * Deployment-scoped management (assign, toggle, execute, scores) lives in
 * DeploymentSkillController and SkillExecutionController.
 */
class SkillCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Cache skill catalog for 10 minutes; tag-invalidated when skills change.
        $cacheKey = 'skill_catalog:'.TenantContext::id().':'.md5($request->getQueryString() ?? '');

        $skills = TaggableCache::remember(['skills', 'catalog'], $cacheKey, 600, function () use ($request) {
            return AgentSkill::active()
                ->when($request->filled('department'), fn ($q) => $q->forDepartment($request->department))
                ->when($request->filled('risk_level'), fn ($q) => $q->byRisk($request->risk_level))
                ->when($request->filled('layer'), fn ($q) => $q->forLayer($request->layer))
                ->when($request->filled('approval_required'), fn ($q) => $q->where('approval_required', filter_var($request->approval_required, FILTER_VALIDATE_BOOLEAN)))
                ->orderBy('department')
                ->orderBy('name')
                ->get();
        });

        return response()->json(['data' => $skills]);
    }

    public function show(AgentSkill $skill): JsonResponse
    {
        $this->authorize('view', $skill);

        return response()->json([
            'data' => $skill->load(['permissions', 'requirements']),
        ]);
    }
}
