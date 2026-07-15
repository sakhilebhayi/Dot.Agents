<?php

namespace App\Providers;

use App\Events\AgentCapabilityContractChanged;
use App\Events\AgentChatStarted;
use App\Events\AgentDecommissioned;
use App\Events\AgentDeployed;
use App\Events\AgentDriftDetected;
use App\Events\AgentPaused;
use App\Events\AgentResumed;
use App\Events\AgentTaskCompleted;
use App\Events\AgentTaskFailed;
use App\Events\AgentTaskRated;
use App\Events\AgentUpdated;
use App\Events\ApprovalProcessed;
use App\Events\ApprovalRequested;
use App\Events\SkillApprovalRequested;
use App\Events\SkillAssigned;
use App\Events\SkillExecuted;
use App\Events\SkillExecutionBlocked;
use App\Events\SkillScoreRecorded;
use App\Events\WorkflowCreated;
use App\Events\WorkflowDeleted;
use App\Events\WorkflowSaved;
use App\Events\WorkflowStatusUpdated;
use App\Listeners\AuditSkillExecution;
use App\Listeners\HandleAgentTaskFailed;
use App\Listeners\HandleSkillApprovalRequested;
use App\Listeners\LogAgentChatStarted;
use App\Listeners\LogAgentDecommissionedAudit;
use App\Listeners\LogAgentPausedAudit;
use App\Listeners\LogAgentResumedAudit;
use App\Listeners\LogAgentTaskRated;
use App\Listeners\LogAgentUpdatedAudit;
use App\Listeners\LogDeploymentAudit;
use App\Listeners\LogSkillAssigned;
use App\Listeners\LogSkillBlockedEvent;
use App\Listeners\LogSkillScoreRecorded;
use App\Listeners\LogWorkflowCreated;
use App\Listeners\LogWorkflowDeleted;
use App\Listeners\LogWorkflowLifecycleEvents;
use App\Listeners\NotifyOnAgentDrift;
use App\Listeners\NotifyOnApprovalProcessed;
use App\Listeners\ProvisionSCCSSkillsAndScorecard;
use App\Listeners\RecordSkillScoreOnExecution;
use App\Listeners\SendApprovalNotification;
use App\Listeners\TriggerCapabilityContractGovernanceReview;
use App\Listeners\UpdateReputationOnTaskComplete;
use App\Listeners\UpdateReputationOnTaskFailed;
use App\Listeners\UpdateScorecardOnTaskComplete;
use App\Listeners\WarmupAgentOnDeployment;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Agent, Skill, and Workflow domain event bindings.
 */
class AgentEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AgentDeployed::class => [
            LogDeploymentAudit::class,
            WarmupAgentOnDeployment::class,
            ProvisionSCCSSkillsAndScorecard::class,
        ],

        AgentPaused::class => [
            LogAgentPausedAudit::class,
        ],

        AgentResumed::class => [
            LogAgentResumedAudit::class,
        ],

        AgentTaskRated::class => [
            LogAgentTaskRated::class,
        ],

        AgentChatStarted::class => [
            LogAgentChatStarted::class,
        ],

        AgentCapabilityContractChanged::class => [
            TriggerCapabilityContractGovernanceReview::class,
        ],

        AgentUpdated::class => [
            LogAgentUpdatedAudit::class,
        ],

        AgentDecommissioned::class => [
            LogAgentDecommissionedAudit::class,
        ],

        AgentTaskCompleted::class => [
            UpdateScorecardOnTaskComplete::class,
            UpdateReputationOnTaskComplete::class,
        ],

        AgentTaskFailed::class => [
            HandleAgentTaskFailed::class,
            UpdateReputationOnTaskFailed::class,
        ],

        AgentDriftDetected::class => [
            NotifyOnAgentDrift::class,
        ],

        ApprovalRequested::class => [
            SendApprovalNotification::class,
        ],

        ApprovalProcessed::class => [
            NotifyOnApprovalProcessed::class,
        ],

        SkillExecuted::class => [
            RecordSkillScoreOnExecution::class,
            AuditSkillExecution::class,
        ],

        SkillExecutionBlocked::class => [
            LogSkillBlockedEvent::class,
        ],

        SkillApprovalRequested::class => [
            SendApprovalNotification::class,
            HandleSkillApprovalRequested::class,
        ],

        SkillAssigned::class => [
            LogSkillAssigned::class,
        ],

        SkillScoreRecorded::class => [
            LogSkillScoreRecorded::class,
        ],

        WorkflowCreated::class => [
            LogWorkflowCreated::class,
        ],

        WorkflowDeleted::class => [
            LogWorkflowDeleted::class,
        ],

        WorkflowSaved::class => [
            LogWorkflowLifecycleEvents::class.'@handleWorkflowSaved',
        ],

        WorkflowStatusUpdated::class => [
            LogWorkflowLifecycleEvents::class.'@handleWorkflowStatusUpdated',
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
