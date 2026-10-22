<?php

namespace Tests\Feature\ViewerRole;

use App\Actions\Ecosystem\SyncTeamsFromInfoDot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Laravel\Jetstream\Jetstream;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesRoleScenarios;
use Tests\TestCase;

/**
 * The viewer role itself, and how InfoDot roles map onto this app's roles.
 */
class ViewerRoleTest extends TestCase
{
    use MakesRoleScenarios;
    use RefreshDatabase;

    /** InfoDot's launch page, as the browser reports it. */
    private function fromInfodot(): array
    {
        $infodot = parse_url((string) config('ecosystem.infodot.redeem_url', ''), PHP_URL_HOST);

        if (is_string($infodot) && $infodot !== '') {
            return ['Origin' => 'https://'.$infodot];
        }

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return ['Origin' => 'https://'.(str_contains($host, '.') ? substr($host, strpos($host, '.') + 1) : $host)];
    }

    private const URL = 'https://infodot.test/api/ecosystem/redeem';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ecosystem.infodot' => [
            'redeem_url' => self::URL, 'platform' => 'agents', 'secret' => 'secret-for-tests', 'timeout' => 2, 'provision' => true,
        ]]);
    }

    // ── the role ─────────────────────────────────────────────────────────

    public function test_viewer_is_a_read_only_role(): void
    {
        $role = Jetstream::findRole('viewer');

        $this->assertNotNull($role);
        $this->assertSame('Viewer', $role->name);
        $this->assertSame(['read'], $role->permissions);
    }

    public function test_admin_and_editor_are_unchanged(): void
    {
        $this->assertSame(['create', 'read', 'update', 'delete'], Jetstream::findRole('admin')->permissions);
        $this->assertSame(['read', 'create', 'update'], Jetstream::findRole('editor')->permissions);
        $this->assertSame(['admin', 'editor', 'viewer'], collect(Jetstream::$roles)->pluck('key')->all());
    }

    public function test_a_viewer_member_holds_the_read_permission_and_nothing_else(): void
    {
        $this->setUpCompany();
        $viewer = $this->memberWith('viewer');

        $this->assertTrue($viewer->hasTeamPermission($this->company, 'read'));

        foreach (['create', 'update', 'delete'] as $permission) {
            $this->assertFalse($viewer->hasTeamPermission($this->company, $permission), $permission);
        }
    }

    public function test_the_role_picker_offers_viewer_to_the_team_owner(): void
    {
        $this->setUpCompany();

        Livewire::actingAs($this->companyOwner)
            ->test(TeamMemberManager::class, ['team' => $this->company])
            ->assertSee('Viewer')
            ->assertSee('Viewer users can read everything');
    }

    public function test_an_owner_can_add_and_change_a_member_to_viewer(): void
    {
        $this->setUpCompany();
        $member = User::factory()->create();
        $this->company->users()->attach($member->id, ['role' => 'editor']);

        Livewire::actingAs($this->companyOwner)
            ->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('managingRoleFor', $member)
            ->set('currentRole', 'viewer')
            ->call('updateRole');

        $this->assertSame('viewer', $member->fresh()->teamRole($this->company->fresh())->key);

        Livewire::actingAs($this->companyOwner)
            ->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('addTeamMemberForm', ['email' => 'newcomer@example.com', 'role' => 'viewer'])
            ->call('addTeamMember')
            ->assertHasNoErrors();

        $this->assertSame('viewer', $this->company->fresh()->teamInvitations()->firstOrFail()->role);
    }

    // ── the mapping ──────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function infodotRoles(): array
    {
        return [
            'owner' => ['owner', 'admin'],
            'admin' => ['admin', 'admin'],
            'editor' => ['editor', 'editor'],
            'viewer' => ['viewer', 'viewer'],
            'member (unknown)' => ['member', 'viewer'],
            'empty' => ['', 'viewer'],
            'nonsense' => ['superuser', 'viewer'],
            'case matters' => ['Admin', 'viewer'],
        ];
    }

    #[DataProvider('infodotRoles')]
    public function test_infodot_role_maps_to_a_local_role(string $infodot, string $local): void
    {
        $this->assertSame($local, (new SyncTeamsFromInfoDot)->localRole($infodot));
        $this->assertNotNull(Jetstream::findRole($local), 'the mapped role exists in this app');
    }

    private function signIn(string $email, string $role, string $name = 'Acme Ltd'): void
    {
        Http::swap(new Factory);
        Http::fake([self::URL => Http::response([
            'user' => ['id' => 1, 'name' => ucfirst(strstr($email, '@', true)), 'email' => $email, 'email_verified' => true],
            'teams' => [['id' => 1, 'uuid' => self::ACME, 'name' => $name, 'personal' => false, 'role' => $role]],
            'current_team_id' => 1,
            'current_team_uuid' => self::ACME,
        ])]);

        auth()->logout();
        $this->post(route('auth.ecosystem'), ['token' => 'one-time'], $this->fromInfodot())->assertRedirect();
    }

    public function test_signing_in_as_an_infodot_viewer_or_unknown_role_lands_read_only(): void
    {
        $this->signIn('ann@acme.test', 'owner');
        $this->signIn('vic@acme.test', 'viewer');
        $this->signIn('mia@acme.test', 'member');

        $team = Team::where('infodot_team_uuid', self::ACME)->firstOrFail();

        $this->assertSame('viewer', User::where('email', 'vic@acme.test')->first()->teamRole($team)->key);
        $this->assertSame('viewer', User::where('email', 'mia@acme.test')->first()->teamRole($team)->key);
        $this->assertTrue(User::where('email', 'ann@acme.test')->first()->ownsTeam($team), 'the owner keeps owning the company');
    }

    public function test_the_next_sign_in_corrects_a_stored_editor_who_is_a_viewer_in_infodot(): void
    {
        $this->signIn('ann@acme.test', 'owner');
        $team = Team::where('infodot_team_uuid', self::ACME)->firstOrFail();

        // How such a person is stored today: the old mapping put an InfoDot viewer on editor.
        $vic = User::factory()->create(['email' => 'vic@acme.test']);
        $team->users()->attach($vic->id, ['role' => 'editor']);

        // Nothing changes until they sign in: no migration rewrites stored roles.
        $this->assertSame('editor', $vic->fresh()->teamRole($team->fresh())->key);

        $this->signIn('vic@acme.test', 'viewer');

        $this->assertSame('viewer', $vic->fresh()->teamRole($team->fresh())->key);
    }

    public function test_the_next_sign_in_also_promotes_when_infodot_says_editor(): void
    {
        $this->signIn('ann@acme.test', 'owner');
        $team = Team::where('infodot_team_uuid', self::ACME)->firstOrFail();
        $vic = User::factory()->create(['email' => 'vic@acme.test']);
        $team->users()->attach($vic->id, ['role' => 'viewer']);

        $this->signIn('vic@acme.test', 'editor');

        $this->assertSame('editor', $vic->fresh()->teamRole($team->fresh())->key);
    }
}
