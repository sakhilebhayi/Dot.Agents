<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Services\AI\ModelRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRouterServiceTest extends TestCase
{
    use RefreshDatabase;

    private ModelRouterService $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new ModelRouterService;
    }

    public function test_gpt_model_routes_to_openai(): void
    {
        $deployment = $this->makeDeployment('gpt-4o');

        $config = $this->router->resolve($deployment);

        $this->assertSame('openai', $config['provider']);
        $this->assertSame('gpt-4o', $config['model']);
    }

    public function test_claude_model_routes_to_anthropic(): void
    {
        $deployment = $this->makeDeployment('claude-3-5-sonnet-20241022');

        $config = $this->router->resolve($deployment);

        $this->assertSame('anthropic', $config['provider']);
    }

    public function test_gemini_model_routes_to_google(): void
    {
        $deployment = $this->makeDeployment('gemini-1.5-pro');

        $config = $this->router->resolve($deployment);

        $this->assertSame('google', $config['provider']);
    }

    public function test_llama_model_routes_to_ollama(): void
    {
        $deployment = $this->makeDeployment('llama3.2');

        $config = $this->router->resolve($deployment);

        $this->assertSame('ollama', $config['provider']);
    }

    public function test_mistral_model_routes_to_ollama(): void
    {
        $deployment = $this->makeDeployment('mistral-7b');

        $config = $this->router->resolve($deployment);

        $this->assertSame('ollama', $config['provider']);
    }

    public function test_unknown_model_defaults_to_openai(): void
    {
        $deployment = $this->makeDeployment('unknown-model-xyz');

        $config = $this->router->resolve($deployment);

        $this->assertSame('openai', $config['provider']);
    }

    public function test_resolve_returns_required_config_keys(): void
    {
        $deployment = $this->makeDeployment('gpt-4o');

        $config = $this->router->resolve($deployment);

        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('provider', $config);
        $this->assertArrayHasKey('temperature', $config);
        $this->assertArrayHasKey('max_tokens', $config);
    }

    public function test_model_override_takes_precedence_over_agent_model(): void
    {
        $deployment = $this->makeDeployment('gpt-4o', modelOverride: 'claude-3-5-sonnet-20241022');

        $config = $this->router->resolve($deployment);

        $this->assertSame('claude-3-5-sonnet-20241022', $config['model']);
        $this->assertSame('anthropic', $config['provider']);
    }

    public function test_failover_chain_includes_anthropic_and_google_when_their_api_keys_are_configured(): void
    {
        config([
            'prism.providers.anthropic.api_key' => 'test-anthropic-key',
            'prism.providers.gemini.api_key' => 'test-gemini-key',
        ]);

        $deployment = $this->makeDeployment('gpt-4o');

        $chain = $this->router->buildFailoverChain($deployment);
        $providers = array_column($chain, 'provider');

        $this->assertContains('anthropic', $providers);
        $this->assertContains('google', $providers);

        $anthropicLeg = $chain[array_search('anthropic', $providers, true)];
        $googleLeg = $chain[array_search('google', $providers, true)];

        $this->assertSame('test-anthropic-key', $anthropicLeg['api_key']);
        $this->assertSame('test-gemini-key', $googleLeg['api_key']);
    }

    /**
     * Build a minimal AgentDeployment mock (not persisted) with the given model.
     */
    private function makeDeployment(string $agentModel, ?string $modelOverride = null): AgentDeployment
    {
        $agent = new Agent;
        $agent->primary_model = $agentModel;
        $agent->defaultPersona = null;

        $deployment = new AgentDeployment;
        $deployment->model_override = $modelOverride;
        $deployment->model_config_override = null;
        $deployment->setRelation('agent', $agent);

        return $deployment;
    }
}
