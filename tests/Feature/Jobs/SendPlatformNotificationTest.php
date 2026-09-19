<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendPlatformNotification;
use App\Models\Organization;
use App\Models\PlatformNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendPlatformNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_a_notification_with_a_real_priority_column(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        (new SendPlatformNotification(
            userId: $user->id,
            organizationId: $organization->id,
            type: 'agent_deployed',
            title: 'Test notification',
            message: 'Something happened',
            severity: 'critical',
            data: ['foo' => 'bar'],
            actionUrl: '/agents/1',
        ))->handle();

        $notification = PlatformNotification::where('user_id', $user->id)->firstOrFail();

        $this->assertSame($organization->id, $notification->organization_id);
        $this->assertSame('agent_deployed', $notification->type);
        $this->assertSame('Test notification', $notification->title);
        $this->assertSame('Something happened', $notification->body);
        $this->assertSame('urgent', $notification->priority);
        $this->assertSame(['foo' => 'bar'], $notification->data);
        $this->assertSame('/agents/1', $notification->action_url);
        $this->assertNull($notification->read_at);
    }

    #[Test]
    public function severity_maps_to_the_real_priority_values(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        $cases = [
            'critical' => 'urgent',
            'error' => 'high',
            'high' => 'high',
            'warning' => 'normal',
            'info' => 'low',
            'success' => 'low',
        ];

        foreach ($cases as $severity => $expectedPriority) {
            (new SendPlatformNotification(
                userId: $user->id,
                organizationId: $organization->id,
                type: "test_{$severity}",
                title: "Title {$severity}",
                message: 'Body',
                severity: $severity,
            ))->handle();

            $this->assertDatabaseHas('platform_notifications', [
                'user_id' => $user->id,
                'type' => "test_{$severity}",
                'priority' => $expectedPriority,
            ]);
        }
    }

    #[Test]
    public function it_does_not_create_a_duplicate_within_five_minutes(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->id]);

        $job = fn () => new SendPlatformNotification(
            userId: $user->id,
            organizationId: $organization->id,
            type: 'agent_deployed',
            title: 'Repeat notification',
            message: 'Body',
        );

        $job()->handle();
        $job()->handle();

        $this->assertSame(1, PlatformNotification::where('user_id', $user->id)->count());
    }

    #[Test]
    public function to_admins_notifies_every_owner_and_admin_of_the_organization(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $owner->id]);

        $organization->users()->attach($owner->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        $organization->users()->attach($admin->id, ['role' => 'admin', 'is_primary' => false, 'joined_at' => now()]);
        $organization->users()->attach($member->id, ['role' => 'member', 'is_primary' => false, 'joined_at' => now()]);

        SendPlatformNotification::toAdmins(
            organizationId: $organization->id,
            type: 'security_alert',
            title: 'Alert',
            message: 'Something needs attention',
        );

        $this->assertDatabaseHas('platform_notifications', ['user_id' => $owner->id, 'type' => 'security_alert']);
        $this->assertDatabaseHas('platform_notifications', ['user_id' => $admin->id, 'type' => 'security_alert']);
        $this->assertDatabaseMissing('platform_notifications', ['user_id' => $member->id, 'type' => 'security_alert']);
    }
}
