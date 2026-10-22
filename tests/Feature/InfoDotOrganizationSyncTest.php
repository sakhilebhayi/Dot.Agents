<?php

namespace Tests\Feature;

use App\Actions\Ecosystem\SyncOrganizationsFromInfoDot;
use App\Events\OrganizationCreated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SignsInThroughInfoDot;
use Tests\TestCase;

/**
 * Every InfoDot company gets exactly one organization here (every data policy
 * checks organization membership), and each person's organization role follows
 * their InfoDot role at every sign-in. Only organizations that carry an
 * infodot_team_uuid are ever touched.
 */
class InfoDotOrganizationSyncTest extends TestCase
{
    use RefreshDatabase;
    use SignsInThroughInfoDot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureInfoDot();
    }

    private function companyOrg(string $uuid = self::ACME): Organization
    {
        return Organization::where('infodot_team_uuid', $uuid)->firstOrFail();
    }

    private function roleOf(string $email, Organization $organization): ?string
    {
        $user = User::where('email', $email)->firstOrFail();

        return $organization->users()->where('users.id', $user->id)->first()?->pivot->role;
    }

    /** A person with a personal organization they own, like OrganizationContextMiddleware makes. */
    private function personalOrganization(User $user): Organization
    {
        $org = Organization::factory()->create(['owner_id' => $user->id, 'infodot_team_uuid' => null]);
        $org->users()->attach($user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        return $org;
    }

    public function test_an_owner_creates_the_company_organization_once_and_is_its_owner(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);

        $org = $this->companyOrg();
        $ann = User::where('email', 'ann@acme.test')->firstOrFail();

        $this->assertSame('Acme Ltd', $org->name);
        $this->assertSame($ann->id, $org->owner_id);
        $this->assertSame('owner', $this->roleOf('ann@acme.test', $org));
        $this->assertSame(1, Organization::count());
    }

    public function test_syncing_again_and_other_members_never_create_a_second_organization(): void
    {
        $company = $this->company(self::ACME, 'Acme Ltd', 'owner');
        $this->signIn('ann@acme.test', [$company], self::ACME);
        $this->signIn('ann@acme.test', [$company], self::ACME);
        $this->signIn('ben@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'admin')], self::ACME);

        $this->assertSame(1, Organization::where('infodot_team_uuid', self::ACME)->count());
        $this->assertSame(1, Organization::count());
        $this->assertSame(2, $this->companyOrg()->users()->count());
    }

    public function test_an_ordinary_member_cannot_create_the_organization_but_is_attached_once_it_exists(): void
    {
        $this->signIn('bob@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'editor')], self::ACME);
        $this->assertSame(0, Organization::whereNotNull('infodot_team_uuid')->count(), 'a member must not become the creator of someone else\'s company');

        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->signIn('bob@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'editor')], self::ACME);

        $this->assertSame('member', $this->roleOf('bob@acme.test', $this->companyOrg()));
        $this->assertSame(1, Organization::where('infodot_team_uuid', self::ACME)->count());
    }

    /** @return array<string, array{string, string}> */
    public static function roleMapping(): array
    {
        return [
            'owner' => ['owner', 'owner'],
            'admin' => ['admin', 'admin'],
            'editor' => ['editor', 'member'],
            'viewer' => ['viewer', 'viewer'],
            'member (not an InfoDot role)' => ['member', 'viewer'],
            'empty' => ['', 'viewer'],
            'nonsense' => ['superuser', 'viewer'],
            'wrong case' => ['Admin', 'viewer'],
        ];
    }

    #[DataProvider('roleMapping')]
    public function test_each_infodot_role_maps_to_the_organization_role(string $infodot, string $expected): void
    {
        $this->assertSame($expected, (new SyncOrganizationsFromInfoDot)->localRole($infodot));
    }

    public function test_each_role_lands_in_the_organization_pivot_after_sign_in(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);

        foreach (['admin' => 'admin', 'editor' => 'member', 'viewer' => 'viewer', 'weird' => 'viewer'] as $infodot => $local) {
            $this->signIn("{$infodot}@acme.test", [$this->company(self::ACME, 'Acme Ltd', $infodot)], self::ACME);
            $this->assertSame($local, $this->roleOf("{$infodot}@acme.test", $this->companyOrg()), $infodot);
        }
    }

    public function test_a_changed_role_in_infodot_is_applied_at_the_next_sign_in(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);

        $this->signIn('cat@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'editor')], self::ACME);
        $this->assertSame('member', $this->roleOf('cat@acme.test', $this->companyOrg()));

        $this->signIn('cat@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'viewer')], self::ACME);
        $this->assertSame('viewer', $this->roleOf('cat@acme.test', $this->companyOrg()));

        $this->signIn('cat@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'admin')], self::ACME);
        $this->assertSame('admin', $this->roleOf('cat@acme.test', $this->companyOrg()));
        $this->assertSame(2, $this->companyOrg()->users()->count());
    }

    public function test_a_demoted_creator_is_read_only_even_though_the_owner_id_column_still_names_them(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'viewer')], self::ACME);

        $this->assertSame('viewer', $this->roleOf('ann@acme.test', $this->companyOrg()));
    }

    public function test_removal_in_infodot_drops_only_that_company_organization(): void
    {
        $this->signIn('gina@globex.test', [$this->company(self::GLOBEX, 'Globex Corp', 'owner')], self::GLOBEX);
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);

        $both = [$this->company(self::ACME, 'Acme Ltd', 'editor'), $this->company(self::GLOBEX, 'Globex Corp', 'editor')];
        $this->signIn('dan@both.test', $both, self::ACME);
        $dan = User::where('email', 'dan@both.test')->firstOrFail();
        $personal = $this->personalOrganization($dan);
        $manual = Organization::factory()->create(['infodot_team_uuid' => null]);
        $manual->users()->attach($dan->id, ['role' => 'admin', 'is_primary' => false, 'joined_at' => now()]);

        $this->signIn('dan@both.test', [$this->company(self::GLOBEX, 'Globex Corp', 'editor')], self::GLOBEX);

        $this->assertNull($this->roleOf('dan@both.test', $this->companyOrg(self::ACME)), 'removed from Acme in InfoDot');
        $this->assertSame('member', $this->roleOf('dan@both.test', $this->companyOrg(self::GLOBEX)));
        $this->assertSame('owner', $this->roleOf('dan@both.test', $personal), 'personal organization untouched');
        $this->assertSame('admin', $this->roleOf('dan@both.test', $manual), 'manually joined organization untouched');
        $this->assertSame(2, Organization::whereNotNull('infodot_team_uuid')->count(), 'the Acme organization itself (and its data) stays');
    }

    public function test_a_person_with_no_companies_keeps_their_personal_and_manual_organizations(): void
    {
        $this->signIn('eve@else.test', []);
        $eve = User::where('email', 'eve@else.test')->firstOrFail();
        $personal = $this->personalOrganization($eve);
        $manual = Organization::factory()->create(['infodot_team_uuid' => null]);
        $manual->users()->attach($eve->id, ['role' => 'member', 'is_primary' => false, 'joined_at' => now()]);

        $this->signIn('eve@else.test', []);
        $this->signIn('eve@else.test', [$this->company(self::ACME, 'Ann personal', 'owner', true)]);

        $this->assertSame('owner', $this->roleOf('eve@else.test', $personal));
        $this->assertSame('member', $this->roleOf('eve@else.test', $manual));
        $this->assertSame(0, Organization::whereNotNull('infodot_team_uuid')->count(), 'personal teams in the payload never become organizations');
    }

    public function test_the_creators_own_organization_is_not_kept_when_infodot_stops_listing_the_company(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->signIn('ann@acme.test', []);

        $this->assertNull($this->roleOf('ann@acme.test', $this->companyOrg()));
    }

    public function test_the_sign_ins_current_company_becomes_the_session_organization(): void
    {
        $this->signIn('gina@globex.test', [$this->company(self::GLOBEX, 'Globex Corp', 'owner')], self::GLOBEX);
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->assertSame($this->companyOrg(self::ACME)->id, session('current_organization_id'));

        $both = [$this->company(self::ACME, 'Acme Ltd', 'editor'), $this->company(self::GLOBEX, 'Globex Corp', 'viewer')];
        $this->signIn('dan@both.test', $both, self::GLOBEX);
        $this->assertSame($this->companyOrg(self::GLOBEX)->id, session('current_organization_id'));

        $this->signIn('dan@both.test', $both, self::ACME);
        $this->assertSame($this->companyOrg(self::ACME)->id, session('current_organization_id'));
    }

    public function test_the_session_organization_is_not_changed_when_the_current_company_is_not_one_the_person_belongs_to(): void
    {
        $this->signIn('gina@globex.test', [$this->company(self::GLOBEX, 'Globex Corp', 'owner')], self::GLOBEX);

        session()->flush();
        // InfoDot claims Eve is acting for Globex but lists no membership of it.
        $this->signIn('eve@else.test', [], self::GLOBEX);

        $this->assertNull(session('current_organization_id'));
    }

    public function test_an_organization_the_person_just_lost_is_forgotten_by_the_session(): void
    {
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->signIn('bob@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'editor')], self::ACME);
        $this->assertSame($this->companyOrg()->id, session('current_organization_id'));

        session(['current_organization_id' => $this->companyOrg()->id]);
        $this->signIn('bob@acme.test', [], null);

        $this->assertNull(session('current_organization_id'));
    }

    public function test_company_renames_follow_infodot_and_a_unique_slug_is_made(): void
    {
        Organization::factory()->create(['slug' => 'acme-ltd', 'infodot_team_uuid' => null]);

        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->assertNotSame('acme-ltd', $this->companyOrg()->slug);

        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Holdings', 'owner')], self::ACME);
        $this->assertSame('Acme Holdings', $this->companyOrg()->name);
        $this->assertSame(1, Organization::where('infodot_team_uuid', self::ACME)->count());
    }

    public function test_creating_a_company_organization_sends_no_welcome_mail_event(): void
    {
        Event::fake([OrganizationCreated::class]);

        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);

        Event::assertNotDispatched(OrganizationCreated::class);
        $this->assertSame(1, Organization::count());
    }

    // ── infodot:link-organization ────────────────────────────────────────

    public function test_link_organization_attaches_an_existing_organization_and_refuses_bad_links(): void
    {
        $existing = Organization::factory()->create(['infodot_team_uuid' => null]);

        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => self::ACME])->assertSuccessful();
        $this->assertSame(self::ACME, $existing->fresh()->infodot_team_uuid);

        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => self::ACME])->assertSuccessful();
        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => self::GLOBEX])->assertFailed();
        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => 'not-a-uuid'])->assertFailed();
        $this->artisan('infodot:link-organization', ['organization' => 999999, 'uuid' => self::GLOBEX])->assertFailed();

        $other = Organization::factory()->create(['infodot_team_uuid' => null]);
        $this->artisan('infodot:link-organization', ['organization' => $other->id, 'uuid' => self::ACME])->assertFailed();
        $this->assertNull($other->fresh()->infodot_team_uuid);
        $this->assertSame(self::ACME, $existing->fresh()->infodot_team_uuid);
    }

    public function test_link_organization_dry_run_changes_nothing_and_lists_the_members(): void
    {
        $existing = Organization::factory()->create(['infodot_team_uuid' => null]);
        $member = User::factory()->create(['email' => 'zed@manual.test']);
        $existing->users()->attach($member->id, ['role' => 'admin', 'is_primary' => false, 'joined_at' => now()]);

        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => self::ACME, '--dry-run' => true])
            ->expectsOutputToContain('zed@manual.test (admin)')
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();

        $this->assertNull($existing->fresh()->infodot_team_uuid);
        $this->assertSame('admin', $this->roleOf('zed@manual.test', $existing));
    }

    public function test_after_linking_the_next_sign_in_reuses_the_organization_and_applies_infodot_roles(): void
    {
        $owner = User::factory()->create(['email' => 'ann@acme.test']);
        $existing = Organization::factory()->create(['owner_id' => $owner->id, 'infodot_team_uuid' => null, 'name' => 'Old name']);
        $existing->users()->attach($owner->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        $before = Organization::count();

        $this->artisan('infodot:link-organization', ['organization' => $existing->id, 'uuid' => self::ACME])->assertSuccessful();
        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'viewer')], self::ACME);

        $this->assertSame($before, Organization::count());
        $this->assertSame('viewer', $this->roleOf('ann@acme.test', $existing), 'the sync lowers a linked organization\'s role to what InfoDot says');
        $this->assertSame($existing->id, session('current_organization_id'));
    }

    // ── migration ────────────────────────────────────────────────────────

    public function test_the_column_exists_nullable_and_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('organizations', 'infodot_team_uuid'));

        Organization::factory()->count(2)->create(['infodot_team_uuid' => null]); // many unlinked

        Organization::factory()->create(['infodot_team_uuid' => self::ACME]);
        $this->expectException(QueryException::class);
        Organization::factory()->create(['infodot_team_uuid' => self::ACME]);
    }

    public function test_the_migration_is_guarded_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_22_000001_add_infodot_team_uuid_to_organizations_table.php');

        $migration->up(); // already there: a no-op, not an error
        $this->assertTrue(Schema::hasColumn('organizations', 'infodot_team_uuid'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('organizations', 'infodot_team_uuid'));
        $migration->down(); // already gone: a no-op

        $migration->up();
        $this->assertTrue(Schema::hasColumn('organizations', 'infodot_team_uuid'));
    }
}
