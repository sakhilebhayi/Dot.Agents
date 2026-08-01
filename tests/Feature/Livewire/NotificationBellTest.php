<?php

namespace Tests\Feature\Livewire;

use App\Livewire\NotificationBell;
use App\Models\Organization;
use App\Models\PlatformNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationBellTest extends TestCase
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
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertOk();
    }

    #[Test]
    public function it_starts_closed(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->assertSet('open', false);
    }

    #[Test]
    public function toggle_opens_and_closes_the_dropdown(): void
    {
        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->call('toggle')
            ->assertSet('open', false);
    }

    #[Test]
    public function unread_count_reflects_unread_notifications_for_the_user(): void
    {
        PlatformNotification::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);

        PlatformNotification::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'read_at' => now(),
        ]);

        $component = Livewire::actingAs($this->user)->test(NotificationBell::class);

        $this->assertEquals(3, $component->get('unreadCount'));
    }

    #[Test]
    public function mark_as_read_updates_the_notification(): void
    {
        $notification = PlatformNotification::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function mark_as_read_does_not_allow_reading_another_users_notification(): void
    {
        $otherUser = User::factory()->create();
        $this->organization->users()->attach($otherUser->id, ['role' => 'member', 'joined_at' => now()]);

        $notification = PlatformNotification::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $otherUser->id,
            'read_at' => null,
        ]);

        // markAsRead scopes the query to the acting user — findOrFail throws
        // ModelNotFoundException for another user's notification.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $notification->id);
    }

    #[Test]
    public function mark_all_as_read_clears_unread_count(): void
    {
        PlatformNotification::factory()->count(4)->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'read_at' => null,
        ]);

        $component = Livewire::actingAs($this->user)->test(NotificationBell::class);
        $this->assertEquals(4, $component->get('unreadCount'));

        $component->call('markAllAsRead');

        $this->assertEquals(0, $component->get('unreadCount'));
    }
}
