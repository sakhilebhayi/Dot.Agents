<?php

namespace Tests\Feature\Agents;

use App\Livewire\Organizations\OrgMemberManager;
use App\Mail\PersonInvitationEmail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Organizations\PersonInvitations;
use App\Support\OrgRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->business = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->business->users()->attach($this->owner->id, ['role' => OrgRoles::OWNER, 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->business->id]);
    }

    private function invite(string $email, string $role = OrgRoles::OPERATOR): string
    {
        return basename(app(PersonInvitations::class)->send($this->business, $email, $role, $this->owner)['link']);
    }

    public function test_inviting_mails_a_person_without_an_account_and_stores_no_raw_token(): void
    {
        Mail::fake();

        Livewire::actingAs($this->owner)->test(OrgMemberManager::class)
            ->set('inviteEmail', 'New.Person@example.com')->call('invite')->assertHasNoErrors();

        Mail::assertSent(PersonInvitationEmail::class, fn ($mail) => $mail->hasTo('new.person@example.com'));

        $row = OrganizationInvitation::firstOrFail();
        $this->assertSame(64, strlen($row->token_hash));
        Mail::assertSent(PersonInvitationEmail::class, fn ($mail) => ! str_contains($mail->link, $row->token_hash));
        $this->assertSame(1, $this->business->users()->count());
    }

    public function test_a_failed_email_hands_the_link_to_the_person_inviting(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('no mail server'));

        $sent = app(PersonInvitations::class)->send($this->business, 'a@example.com', OrgRoles::VIEWER, $this->owner);

        $this->assertFalse($sent['mailed']);
        $this->assertStringContainsString('/invitations/', $sent['link']);
    }

    public function test_opening_the_link_changes_nothing_and_joining_needs_the_invited_account(): void
    {
        Mail::fake();
        $token = $this->invite('invited@example.com', OrgRoles::APPROVER);
        $invited = User::factory()->create(['email' => 'Invited@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($invited)->get("/invitations/{$token}")->assertOk()->assertSee($this->business->name);
        $this->assertFalse($this->business->users()->where('users.id', $invited->id)->exists());

        $this->actingAs($other)->post("/invitations/{$token}")->assertForbidden();
        $this->assertFalse($this->business->users()->where('users.id', $other->id)->exists());

        $this->actingAs($invited)->post("/invitations/{$token}")->assertRedirect(route('dashboard'));
        $this->assertSame(OrgRoles::APPROVER, $invited->roleInOrganization($this->business->id));
    }

    public function test_a_link_works_once_and_stops_after_it_expires_or_is_replaced_or_cancelled(): void
    {
        Mail::fake();
        $invited = User::factory()->create(['email' => 'invited@example.com']);

        $first = $this->invite('invited@example.com');
        $second = $this->invite('invited@example.com');
        $this->actingAs($invited)->post("/invitations/{$first}")->assertNotFound();

        $this->actingAs($invited)->post("/invitations/{$second}")->assertRedirect();
        $this->actingAs($invited)->post("/invitations/{$second}")->assertNotFound();

        $late = $this->invite('late@example.com');
        OrganizationInvitation::where('email', 'late@example.com')->update(['expires_at' => now()->subMinute()]);
        $this->actingAs(User::factory()->create(['email' => 'late@example.com']))->post("/invitations/{$late}")->assertNotFound();

        $gone = $this->invite('gone@example.com');
        app(PersonInvitations::class)->cancel($this->business, OrganizationInvitation::where('email', 'gone@example.com')->value('id'));
        $this->actingAs(User::factory()->create(['email' => 'gone@example.com']))->post("/invitations/{$gone}")->assertNotFound();
    }

    public function test_another_business_cannot_cancel_an_invitation(): void
    {
        Mail::fake();
        $this->invite('keep@example.com');
        $elsewhere = Organization::factory()->create();

        app(PersonInvitations::class)->cancel($elsewhere, OrganizationInvitation::firstOrFail()->id);

        $this->assertSame(1, OrganizationInvitation::count());
    }

    public function test_a_guest_is_sent_to_sign_in_and_comes_back(): void
    {
        Mail::fake();
        $token = $this->invite('invited@example.com');

        $this->get("/invitations/{$token}")->assertOk()->assertSee('Sign in with the account for invited@example.com');
        $this->assertSame(url("/invitations/{$token}"), session('url.intended'));
    }

    public function test_only_an_owner_can_invite_an_owner(): void
    {
        $admin = User::factory()->create();
        $this->business->users()->attach($admin->id, ['role' => OrgRoles::ADMIN, 'is_primary' => true, 'joined_at' => now()]);

        Livewire::actingAs($admin)->test(OrgMemberManager::class)
            ->set('inviteEmail', 'x@example.com')->set('inviteRole', OrgRoles::OWNER)->call('invite')->assertForbidden();

        $this->assertSame(0, OrganizationInvitation::count());
    }
}
