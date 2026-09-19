<?php

namespace Tests\Unit\Services\Memory;

use App\Services\Memory\DotMemoryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DotMemoryClientTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config([
            'services.dot_memory.base_url' => 'https://memory.test',
            'services.dot_memory.token' => 'service-account-token',
        ]);
    }

    private function unconfigure(): void
    {
        config([
            'services.dot_memory.base_url' => null,
            'services.dot_memory.token' => null,
        ]);
    }

    private function client(): DotMemoryClient
    {
        return $this->app->make(DotMemoryClient::class);
    }

    // -----------------------------------------------------------------------
    // recordEvent()
    // -----------------------------------------------------------------------

    public function test_record_event_sends_the_expected_envelope_and_returns_true_on_success(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['data' => ['id' => 1]], 201)]);

        $result = $this->client()->recordEvent('loop-1', 'agent', 'agent-42', ['source' => 'orchestrator'], ['note' => 'observed something']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://memory.test/api/intelligence/events'
                && $request->hasHeader('Authorization', 'Bearer service-account-token')
                && $body['loop_id'] === 'loop-1'
                && $body['platform'] === 'dot-agents'
                && $body['subject_type'] === 'agent'
                && $body['subject_id'] === 'agent-42'
                && $body['source'] === 'orchestrator'
                && $body['detail'] === ['note' => 'observed something']
                && is_string($body['event_id']) && strlen($body['event_id']) === 36
                && is_string($body['occurred_at']);
        });
    }

    public function test_record_event_returns_false_and_does_not_throw_on_a_422(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['message' => 'The event id field is required.'], 422)]);

        $result = $this->client()->recordEvent('loop-1', 'agent', 'agent-42');

        $this->assertFalse($result);
    }

    public function test_record_event_returns_false_without_any_http_call_when_not_configured(): void
    {
        $this->unconfigure();
        Http::fake();

        $result = $this->client()->recordEvent('loop-1', 'agent', 'agent-42');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // recordDecision()
    // -----------------------------------------------------------------------

    public function test_record_decision_sends_confidence_risk_and_autonomy_level_and_returns_true_on_success(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['data' => ['id' => 2]], 201)]);

        $result = $this->client()->recordDecision('loop-1', 'agent', 'agent-42', 0.82, 0.15, 'recommend');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://memory.test/api/intelligence/decisions'
                && $body['confidence'] === 0.82
                && $body['risk'] === 0.15
                && $body['autonomy_level'] === 'recommend';
        });
    }

    public function test_record_decision_returns_false_and_does_not_throw_on_a_500(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response('Internal Server Error', 500)]);

        $result = $this->client()->recordDecision('loop-1', 'agent', 'agent-42', 0.5, 0.5, 'observe');

        $this->assertFalse($result);
    }

    public function test_record_decision_returns_false_without_any_http_call_when_not_configured(): void
    {
        $this->unconfigure();
        Http::fake();

        $result = $this->client()->recordDecision('loop-1', 'agent', 'agent-42', 0.5, 0.5, 'observe');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // recordAction()
    // -----------------------------------------------------------------------

    public function test_record_action_sends_action_kind_and_hardcoded_executor_platform_and_returns_true_on_success(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['data' => ['id' => 3]], 201)]);

        $result = $this->client()->recordAction('loop-1', 'agent', 'agent-42', 'send_email', 'succeeded');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://memory.test/api/intelligence/actions'
                && $body['action_kind'] === 'send_email'
                && $body['executor_platform'] === 'dot-agents'
                && $body['execution_status'] === 'succeeded';
        });
    }

    public function test_record_action_returns_false_and_does_not_throw_on_a_422(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['message' => 'validation failed'], 422)]);

        $result = $this->client()->recordAction('loop-1', 'agent', 'agent-42', 'send_email', 'failed');

        $this->assertFalse($result);
    }

    public function test_record_action_returns_false_without_any_http_call_when_not_configured(): void
    {
        $this->unconfigure();
        Http::fake();

        $result = $this->client()->recordAction('loop-1', 'agent', 'agent-42', 'send_email', 'pending');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // recordOutcome()
    // -----------------------------------------------------------------------

    public function test_record_outcome_sends_verdict_and_returns_true_on_success(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['data' => ['id' => 4]], 201)]);

        $result = $this->client()->recordOutcome('loop-1', 'agent', 'agent-42', 'improved', [], ['measure' => 'ticket resolution time']);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://memory.test/api/intelligence/outcomes'
                && $body['verdict'] === 'improved';
        });
    }

    public function test_record_outcome_returns_false_and_does_not_throw_on_a_500(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response('Internal Server Error', 500)]);

        $result = $this->client()->recordOutcome('loop-1', 'agent', 'agent-42', 'worsened');

        $this->assertFalse($result);
    }

    public function test_record_outcome_returns_false_without_any_http_call_when_not_configured(): void
    {
        $this->unconfigure();
        Http::fake();

        $result = $this->client()->recordOutcome('loop-1', 'agent', 'agent-42', 'unchanged');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    // context()
    // -----------------------------------------------------------------------

    public function test_context_returns_the_real_data_payload_on_success(): void
    {
        $this->configure();

        $payload = [
            'subject' => ['type' => 'agent', 'id' => 'agent-42'],
            'known' => true,
            'observations' => 3,
            'first_seen' => '2026-08-01T00:00:00Z',
            'last_seen' => '2026-09-18T00:00:00Z',
            'timeline' => [],
            'what_was_tried' => [],
            'what_worked' => [],
            'recurrence' => [],
            'gaps' => [],
        ];

        Http::fake(['memory.test/*' => Http::response(['data' => $payload], 200)]);

        $result = $this->client()->context('agent', 'agent-42', 'sig-123');

        $this->assertSame($payload, $result);

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://memory.test/api/intelligence/context')
                && $query['subject_type'] === 'agent'
                && $query['subject_id'] === 'agent-42'
                && $query['signature'] === 'sig-123'
                && $query['platform'] === 'dot-agents';
        });
    }

    public function test_context_returns_null_and_logs_a_degraded_warning_on_a_non_2xx_response(): void
    {
        $this->configure();
        Http::fake(['memory.test/*' => Http::response(['message' => 'not found'], 404)]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'degraded'));

        $result = $this->client()->context('agent', 'agent-42');

        $this->assertNull($result);
    }

    public function test_context_returns_null_without_any_http_call_when_not_configured(): void
    {
        $this->unconfigure();
        Http::fake();

        $result = $this->client()->context('agent', 'agent-42');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_context_returns_null_and_does_not_throw_when_the_request_times_out(): void
    {
        $this->configure();
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $result = $this->client()->context('agent', 'agent-42');

        $this->assertNull($result);
    }
}
