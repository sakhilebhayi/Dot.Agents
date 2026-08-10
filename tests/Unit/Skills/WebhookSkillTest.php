<?php

namespace Tests\Unit\Skills;

use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Skills\WebhookSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSkillTest extends TestCase
{
    use RefreshDatabase;

    private function skillRecord(array $overrides = []): AgentSkill
    {
        return AgentSkill::factory()->webhook(organizationId: Organization::factory()->create()->id)->create($overrides);
    }

    public function test_successful_response_maps_to_a_completed_result(): void
    {
        $skill = $this->skillRecord();

        Http::fake([
            $skill->webhook_url => Http::response([
                'status' => 'completed',
                'output' => ['sku_count' => 42],
                'confidence' => 88.5,
                'findings' => ['low stock on SKU-1'],
                'recommendations' => ['reorder SKU-1'],
            ], 200),
        ]);

        $result = (new WebhookSkill($skill))->execute(['query' => 'low stock']);

        $this->assertSame('completed', $result->status);
        $this->assertSame(['sku_count' => 42], $result->output);
        $this->assertSame(88.5, $result->confidence);
        $this->assertSame(['low stock on SKU-1'], $result->findings);
    }

    public function test_webhook_reported_failure_maps_to_a_failed_result(): void
    {
        $skill = $this->skillRecord();

        Http::fake([
            $skill->webhook_url => Http::response([
                'status' => 'failed',
                'output' => ['error' => 'inventory API unreachable'],
            ], 200),
        ]);

        $result = (new WebhookSkill($skill))->execute([]);

        $this->assertSame('failed', $result->status);
        $this->assertSame('inventory API unreachable', $result->output['error']);
    }

    public function test_non_2xx_response_maps_to_a_failed_result_not_an_exception(): void
    {
        $skill = $this->skillRecord();

        Http::fake([$skill->webhook_url => Http::response('Internal Server Error', 500)]);

        $result = (new WebhookSkill($skill))->execute([]);

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('HTTP 500', $result->output['error']);
    }

    public function test_connection_exception_maps_to_a_failed_result_not_an_uncaught_throw(): void
    {
        $skill = $this->skillRecord();

        Http::fake([$skill->webhook_url => fn () => throw new ConnectionException('timed out')]);

        $result = (new WebhookSkill($skill))->execute([]);

        $this->assertSame('failed', $result->status);
    }

    public function test_request_body_includes_skill_key_input_and_safe_context_only(): void
    {
        $skill = $this->skillRecord();
        $deployment = AgentDeployment::factory()->create();
        $task = AgentTask::factory()->create(['agent_deployment_id' => $deployment->id]);

        Http::fake([$skill->webhook_url => Http::response(['status' => 'completed', 'output' => []], 200)]);

        (new WebhookSkill($skill))->execute(
            ['query' => 'hi'],
            ['deployment' => $deployment, 'task' => $task, 'phase' => 'pre_task']
        );

        Http::assertSent(function ($request) use ($skill, $deployment, $task) {
            return $request->url() === $skill->webhook_url
                && $request['skill_key'] === $skill->key
                && $request['input'] === ['query' => 'hi']
                && $request['context']['deployment_id'] === $deployment->id
                && $request['context']['task_id'] === $task->id
                && $request['context']['phase'] === 'pre_task';
        });
    }

    public function test_key_and_layer_come_from_the_underlying_skill_record(): void
    {
        $skill = $this->skillRecord(['key' => 'my-custom-skill', 'layer' => 'workforce']);

        $webhookSkill = new WebhookSkill($skill);

        $this->assertSame('my-custom-skill', $webhookSkill->key());
        $this->assertSame('workforce', $webhookSkill->layer());
    }
}
