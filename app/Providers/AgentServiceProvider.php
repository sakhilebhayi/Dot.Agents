<?php

namespace App\Providers;

use App\Services\AI\AgentModelCaller;
use App\Services\AI\AgentOrchestrationService;
use App\Services\AI\AgentPluginService;
use App\Services\AI\AgentQuotaGuard;
use App\Services\AI\AgentSandboxService;
use App\Services\AI\EnterpriseBrainScorer;
use App\Services\AI\EnterpriseBrainService;
use App\Services\AI\ExecutiveCouncilService;
use App\Services\AI\GraphWorkflowEngineService;
use App\Services\AI\MemoryService;
use App\Services\AI\ModelRouterService;
use App\Services\AI\OutputModerationService;
use App\Services\AI\PromptBuilderService;
use App\Services\AI\ResponseProcessorService;
use App\Services\AI\SkillExecutionPipeline;
use App\Services\AI\SkillRegistryService;
use App\Services\AI\ToolPermissionService;
use App\Services\AI\VectorMemoryService;
use App\Services\AI\Workflow\WorkflowGraphResolver;
use App\Services\AI\WorkflowRiskScoringService;
use App\Services\Governance\AgentCharterLoader;
use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use App\Services\Governance\DigitalImmuneSystem;
use App\Services\Governance\EnterpriseConstitutionService;
use App\Services\Memory\DotMemoryClient;
use App\Skills\Governance\AuditLoggingSkill;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelRouterService::class);
        $this->app->singleton(MemoryService::class);
        $this->app->singleton(ToolPermissionService::class);
        $this->app->singleton(OutputModerationService::class);

        $this->app->singleton(AgentSandboxService::class, function ($app) {
            return new AgentSandboxService(
                $app->make(ToolPermissionService::class)
            );
        });

        $this->app->singleton(AgentOrchestrationService::class, function ($app) {
            return new AgentOrchestrationService(
                $app->make(ModelRouterService::class),
                $app->make(DelusionDetectionService::class),
                $app->make(AuditService::class),
                $app->make(MemoryService::class),
                $app->make(AgentSandboxService::class),
                $app->make(OutputModerationService::class),
                $app->make(PromptBuilderService::class),
                $app->make(ResponseProcessorService::class),
                $app->make(AgentModelCaller::class),
                $app->make(AgentQuotaGuard::class),
                $app->make(DotMemoryClient::class),
                $app->make(AgentCharterLoader::class),
                $app->make(DigitalImmuneSystem::class),
            );
        });

        $this->app->singleton(GraphWorkflowEngineService::class, function ($app) {
            return new GraphWorkflowEngineService(
                $app->make(AgentOrchestrationService::class),
                $app->make(AuditService::class),
                $app->make(WorkflowRiskScoringService::class),
                $app->make(WorkflowGraphResolver::class),
            );
        });

        $this->app->singleton(AgentPluginService::class);

        $this->app->singleton(VectorMemoryService::class, function ($app) {
            return new VectorMemoryService(
                $app->make(MemoryService::class),
            );
        });

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

        // Skill Execution Pipeline — wraps task execution with skill hooks
        $this->app->singleton(SkillExecutionPipeline::class, function ($app) {
            return new SkillExecutionPipeline($app->make(SkillRegistryService::class));
        });
    }
}
