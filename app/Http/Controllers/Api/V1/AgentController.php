<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Support\TaggableCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AgentController extends Controller
{
    /** Figures that came with the catalogue rows and were never measured. */
    private const SEEDED = ['accuracy_score', 'reliability_score', 'avg_rating', 'review_count', 'total_deployments', 'pricing_model', 'base_price'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Agent::class);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Cache catalog listings for 10 minutes; tag-invalidated when agents change.
        $cacheKey = 'agent_catalog:'.md5(serialize($validated));

        $agents = TaggableCache::remember(['agents', 'catalog'], $cacheKey, 600, function () use ($validated) {
            return Agent::query()
                ->active()
                ->when(isset($validated['category_id']), fn ($q) => $q->where('category_id', $validated['category_id']))
                ->when(isset($validated['department_id']), fn ($q) => $q->where('department_id', $validated['department_id']))
                ->when(isset($validated['search']), fn ($q) => $q->where(function ($sub) use ($validated) {
                    $term = $validated['search'];
                    $sub->where('name', 'like', '%'.$term.'%')
                        ->orWhere('description', 'like', '%'.$term.'%');
                }))
                ->select([
                    'id', 'uuid', 'name', 'slug', 'tagline', 'avatar', 'version',
                    'agent_type', 'specialization', 'primary_model', 'status', 'is_featured',
                ])
                ->paginate($validated['per_page'] ?? 20);
        });

        return response()->json($agents);
    }

    public function show(Agent $agent): JsonResponse
    {
        $this->authorize('view', $agent);

        if ($agent->status !== 'active') {
            return response()->json(['message' => 'Agent not found'], 404);
        }

        return response()->json($agent->load(['category', 'agentDepartment'])->makeHidden(self::SEEDED));
    }
}
