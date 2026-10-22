<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkOrganizationToInfoDot extends Command
{
    protected $signature = 'infodot:link-organization {organization : This app\'s organization id} {uuid : The InfoDot team uuid} {--dry-run : Show what linking would mean, change nothing}';

    protected $description = 'Link an existing organization to a company in InfoDot';

    public function handle(): int
    {
        $organization = Organization::find($this->argument('organization'));
        $uuid = (string) $this->argument('uuid');

        if (! $organization) {
            $this->error('No such organization.');

            return self::FAILURE;
        }

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            $this->error('That is not a uuid.');

            return self::FAILURE;
        }

        if ($organization->infodot_team_uuid && $organization->infodot_team_uuid !== $uuid) {
            $this->error('This organization is already linked to a different company.');

            return self::FAILURE;
        }

        if (Organization::where('infodot_team_uuid', $uuid)->where('id', '!=', $organization->id)->exists()) {
            $this->error('Another organization here is already linked to that company.');

            return self::FAILURE;
        }

        $this->describe($organization, $uuid);

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        $organization->forceFill(['infodot_team_uuid' => $uuid])->save();
        $this->info("Organization #{$organization->id} \"{$organization->name}\" is now linked to the InfoDot company {$uuid}.");

        return self::SUCCESS;
    }

    private function describe(Organization $organization, string $uuid): void
    {
        $members = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organization->id)
            ->orderBy('organization_user.id')
            ->get(['users.email', 'organization_user.role']);

        $this->line("Organization #{$organization->id} \"{$organization->name}\" has {$members->count()} member(s):");

        foreach ($members as $member) {
            $this->line("  - {$member->email} ({$member->role})");
        }

        $this->warn('Once linked, at each InfoDot sign-in: a member InfoDot lists for the company gets the role InfoDot gives them here (this can lower an existing role); a member InfoDot does not list loses this organization; anyone InfoDot lists is attached. Its name follows InfoDot.');
    }
}
