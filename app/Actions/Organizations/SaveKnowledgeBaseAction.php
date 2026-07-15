<?php

namespace App\Actions\Organizations;

use App\Events\KnowledgeBaseCreated;
use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveKnowledgeBaseAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Organization $organization, array $data): KnowledgeBase
    {
        Gate::authorize('update', $organization);

        $kb = KnowledgeBase::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(6),
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'general',
            'access_level' => $data['access_level'] ?? 'internal',
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->logUserAction(
            event: 'knowledge_base.created',
            description: "Created knowledge base '{$kb->name}'",
            subject: $kb,
        );

        event(new KnowledgeBaseCreated($kb));

        return $kb;
    }
}
