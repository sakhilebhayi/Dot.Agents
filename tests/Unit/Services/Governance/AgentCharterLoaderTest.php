<?php

namespace Tests\Unit\Services\Governance;

use App\Services\Governance\AgentCharterLoader;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AgentCharterLoaderTest extends TestCase
{
    private AgentCharterLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.dot_brain.charters_path' => base_path('tests/Fixtures/dot-brain-charters')]);

        $this->loader = new AgentCharterLoader;
    }

    public function test_returns_chartered_result_for_an_active_charter(): void
    {
        $result = $this->loader->charterFor('governance');

        $this->assertSame([
            'chartered' => true,
            'status' => 'active',
            'trust_score_floor' => 0.6,
            'human_approver' => 'Chief Intelligence Architect',
        ], $result);
    }

    public function test_returns_uncharted_result_for_a_key_with_no_matching_file(): void
    {
        $result = $this->loader->charterFor('ceo-agent');

        $this->assertSame([
            'chartered' => false,
            'status' => null,
            'trust_score_floor' => null,
            'human_approver' => null,
        ], $result);
    }

    public function test_returns_uncharted_result_when_the_charters_directory_does_not_exist(): void
    {
        config(['services.dot_brain.charters_path' => base_path('tests/Fixtures/no-such-dot-brain-checkout')]);

        $result = $this->loader->charterFor('governance');

        $this->assertFalse($result['chartered']);
    }

    public function test_treats_a_non_active_status_as_uncharted(): void
    {
        $result = $this->loader->charterFor('draft-agent');

        $this->assertSame([
            'chartered' => false,
            'status' => null,
            'trust_score_floor' => null,
            'human_approver' => null,
        ], $result);
    }

    public function test_logs_a_warning_and_returns_uncharted_for_malformed_frontmatter(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('AgentCharterLoader: malformed charter frontmatter', \Mockery::type('array'));

        $result = $this->loader->charterFor('malformed-agent');

        $this->assertSame([
            'chartered' => false,
            'status' => null,
            'trust_score_floor' => null,
            'human_approver' => null,
        ], $result);
    }
}
