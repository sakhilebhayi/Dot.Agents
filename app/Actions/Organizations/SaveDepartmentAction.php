<?php

namespace App\Actions\Organizations;

use App\DTOs\Organizations\SaveDepartmentData;
use App\Events\DepartmentSaved;
use App\Models\Department;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveDepartmentAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Organization $organization, SaveDepartmentData $data): Department
    {
        Gate::authorize('update', $organization);

        $payload = [
            'organization_id' => $organization->id,
            'name' => $data->name,
            'slug' => Str::slug($data->name),
            'description' => $data->description,
            'head_name' => $data->headName,
            'is_active' => true,
        ];

        if ($data->type !== null) {
            $payload['type'] = $data->type;
        }

        if ($data->existingId) {
            $dept = Department::findOrFail($data->existingId);
            abort_if($dept->organization_id !== $organization->id, 403);
            $dept->update($payload);
            $dept = $dept->fresh();
        } else {
            $dept = Department::create($payload);
        }

        $this->auditService->logUserAction(
            event: $data->existingId ? 'department.updated' : 'department.created',
            description: ($data->existingId ? 'Updated' : 'Created')." department '{$dept->name}'",
            subject: $dept,
        );

        event(new DepartmentSaved($dept, ! $data->existingId));

        return $dept;
    }
}
