<?php

namespace Tests\Feature\Actions\Organizations;

use App\Actions\Organizations\DeleteDepartmentAction;
use App\Actions\Organizations\SaveDepartmentAction;
use App\DTOs\Organizations\DeleteDepartmentData;
use App\DTOs\Organizations\SaveDepartmentData;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DepartmentActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
        Gate::before(fn () => true);
    }

    // ── SaveDepartmentAction ─────────────────────────────────────────────────

    public function test_save_department_creates_new_department(): void
    {
        $this->actingAs($this->user);

        $data = new SaveDepartmentData(
            name: 'Engineering',
            description: 'Engineering team',
            type: 'technical',
            headName: 'Jane Smith',
        );
        $dept = app(SaveDepartmentAction::class)->execute($this->organization, $data);

        $this->assertInstanceOf(Department::class, $dept);
        $this->assertDatabaseHas('departments', [
            'name' => 'Engineering',
            'organization_id' => $this->organization->id,
            'description' => 'Engineering team',
            'is_active' => true,
        ]);
    }

    public function test_save_department_generates_slug_from_name(): void
    {
        $this->actingAs($this->user);

        $data = new SaveDepartmentData(name: 'Human Resources', type: 'operational');

        $dept = app(SaveDepartmentAction::class)->execute($this->organization, $data);

        $this->assertSame('human-resources', $dept->slug);
    }

    public function test_save_department_updates_existing_department(): void
    {
        $this->actingAs($this->user);

        $dept = Department::create([
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'type' => 'operational',
            'is_active' => true,
        ]);

        $data = new SaveDepartmentData(
            name: 'New Name',
            type: 'technical',
            existingId: $dept->id,
        );

        $result = app(SaveDepartmentAction::class)->execute($this->organization, $data);

        $this->assertSame($dept->id, $result->id);
        $this->assertSame('New Name', $result->name);
        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'New Name']);
    }

    public function test_save_department_rejects_cross_org_update(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $otherDept = Department::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Dept',
            'slug' => 'other-dept',
            'type' => 'operational',
            'is_active' => true,
        ]);

        // The Department model has a global org scope: findOrFail won't find
        // a department belonging to a different org, enforcing tenant isolation.
        $this->expectException(ModelNotFoundException::class);

        app(SaveDepartmentAction::class)->execute(
            $this->organization,
            new SaveDepartmentData(name: 'Hijacked', type: 'operational', existingId: $otherDept->id)
        );
    }

    // ── DeleteDepartmentAction ───────────────────────────────────────────────

    public function test_delete_department_removes_record(): void
    {
        $this->actingAs($this->user);

        $dept = Department::create([
            'organization_id' => $this->organization->id,
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'type' => 'operational',
            'is_active' => true,
        ]);

        app(DeleteDepartmentAction::class)->execute(
            $this->organization,
            DeleteDepartmentData::fromId($dept->id)
        );

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    public function test_delete_department_rejects_cross_org_deletion(): void
    {
        $this->actingAs($this->user);

        $otherOrg = Organization::factory()->create();
        $otherDept = Department::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Other Dept',
            'slug' => 'other-dept',
            'type' => 'operational',
            'is_active' => true,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(DeleteDepartmentAction::class)->execute(
            $this->organization,
            DeleteDepartmentData::fromId($otherDept->id)
        );
    }
}
