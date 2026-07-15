<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\DTOs\Organizations\DeleteDepartmentData;
use App\Events\DepartmentDeleted;
use App\Models\Department;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class DeleteDepartmentAction
{
    public function __construct(private readonly AuditService $auditService) {}

    public function execute(Organization $organization, DeleteDepartmentData $data): void
    {
        Gate::authorize('update', $organization);

        $department = Department::where('organization_id', $organization->id)
            ->findOrFail($data->departmentId);

        $name = $department->name;
        $department->delete();

        $this->auditService->logUserAction(
            event: 'department.deleted',
            description: "Deleted department '{$name}'",
            subject: $organization,
        );

        event(new DepartmentDeleted($organization->id, $name));
    }
}
