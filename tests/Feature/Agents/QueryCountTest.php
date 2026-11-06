<?php

namespace Tests\Feature\Agents;

use App\Livewire\Dashboard\AgentDashboard;
use App\Livewire\Runs\RunCentre;
use App\Livewire\UsageOverview;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** A page must not ask the database once per row. More work on the books must not mean more queries. */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private AgentDeployment $agent;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = AgentDeployment::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->create();
        $this->agent->organization->users()->attach($this->owner, ['role' => 'owner']);
        session(['current_organization_id' => $this->agent->organization_id]);
    }

    private function runs(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            AgentRun::create(['organization_id' => $this->agent->organization_id, 'agent_deployment_id' => $this->agent->id, 'config_version_id' => $this->agent->published_version_id, 'trigger_type' => 'manual', 'status' => $i % 2 ? AgentRun::COMPLETED : AgentRun::FAILED, 'summary' => 'Some work', 'input' => 'x', 'limits' => [], 'started_at' => now()->subMinutes(2), 'finished_at' => now()]);
        }
    }

    private function queries(string $component): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($this->owner)->test($component)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return array<string, array{class-string}> */
    public static function pages(): array
    {
        return ['runs' => [RunCentre::class], 'command centre' => [AgentDashboard::class], 'usage' => [UsageOverview::class]];
    }

    #[DataProvider('pages')]
    public function test_queries_do_not_grow_with_the_rows(string $component): void
    {
        $this->runs(3);
        $few = $this->queries($component);

        $this->runs(30);
        $many = $this->queries($component);

        $this->assertLessThanOrEqual($few + 2, $many);
        $this->assertLessThan(40, $many);
    }
}
