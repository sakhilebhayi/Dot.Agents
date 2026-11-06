<?php

namespace App\Providers;

use App\Services\AI\AgentPluginService;
use App\Services\AI\AgentSandboxService;
use App\Services\AI\EnterpriseBrainScorer;
use App\Services\AI\EnterpriseBrainService;
use App\Services\AI\ExecutiveCouncilService;
use App\Services\AI\MemoryService;
use App\Services\AI\OutputModerationService;
use App\Services\AI\SkillExecutionPipeline;
use App\Services\AI\SkillRegistryService;
use App\Services\AI\ToolPermissionService;
use App\Services\Governance\AuditService;
use App\Services\Governance\EnterpriseConstitutionService;
use App\Services\Models\ModelGateway;
use App\Services\Models\PrismGateway;
use App\Services\Models\ScriptedGateway;
use App\Skills\Governance\AuditLoggingSkill;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MemoryService::class);
        $this->app->singleton(ToolPermissionService::class);
        $this->app->singleton(OutputModerationService::class);

        $this->app->singleton(AgentSandboxService::class, function ($app) {
            return new AgentSandboxService(
                $app->make(ToolPermissionService::class)
            );
        });

        $this->app->singleton(AgentPluginService::class);

        $this->app->singleton(EnterpriseConstitutionService::class);
        $this->app->singleton(ExecutiveCouncilService::class);

        $this->app->singleton(EnterpriseBrainService::class, function ($app) {
            return new EnterpriseBrainService(
                $app->make(EnterpriseConstitutionService::class),
                $app->make(EnterpriseBrainScorer::class),
            );
        });

        // Skill Registry — singleton so the built-in skill map is only constructed once
        $this->app->singleton(SkillRegistryService::class);

        // AuditLoggingSkill needs AuditService — explicit binding so DI resolves correctly
        $this->app->bind(AuditLoggingSkill::class, function ($app) {
            return new AuditLoggingSkill($app->make(AuditService::class));
        });

        // The stand-in model answers only outside production, and only when asked for by name.
        $this->app->singleton(ScriptedGateway::class);
        $this->app->bind(ModelGateway::class, fn ($app) => ! $app->isProduction() && config('prism.default_provider') === 'scripted'
            ? $app->make(ScriptedGateway::class)
            : $app->make(PrismGateway::class));

        // Skill Execution Pipeline — wraps task execution with skill hooks
        $this->app->singleton(SkillExecutionPipeline::class, function ($app) {
            return new SkillExecutionPipeline($app->make(SkillRegistryService::class));
        });
    }
}
