<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\AgentApproval;
use App\Models\AgentCategory;
use App\Models\AgentDepartment;
use App\Models\AgentDeployment;
use App\Models\AgentMemory;
use App\Models\AgentMessage;
use App\Models\AgentPersona;
use App\Models\AgentPlugin;
use App\Models\AgentPluginInstallation;
use App\Models\AgentReview;
use App\Models\AgentRun;
use App\Models\AgentScorecard;
use App\Models\AgentSession;
use App\Models\AgentSkill;
use App\Models\AgentSkillApproval;
use App\Models\AgentSkillAssignment;
use App\Models\AgentSkillAudit;
use App\Models\AgentSkillExecution;
use App\Models\AgentSkillPermission;
use App\Models\AgentSkillRequirement;
use App\Models\AgentSkillScore;
use App\Models\AgentTask;
use App\Models\AgentToolPermission;
use App\Models\AgentVersion;
use App\Models\AgentWorkflow;
use App\Models\AuditLog;
use App\Models\DecisionLog;
use App\Models\Department;
use App\Models\Division;
use App\Models\EnterpriseDecision;
use App\Models\EnterpriseHealthScore;
use App\Models\ExecutiveCouncilSession;
use App\Models\ExecutiveRecommendation;
use App\Models\Invoice;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeBase;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationDNA;
use App\Models\OrganizationSocialCredential;
use App\Models\OrganizationSubscription;
use App\Models\OrganizationTwin;
use App\Models\PlatformMegaScorecard;
use App\Models\PlatformNotification;
use App\Models\SecurityEvent;
use App\Models\SocialAccount;
use App\Models\SocialCampaign;
use App\Models\SocialConnectionSettings;
use App\Models\SocialConversation;
use App\Models\SocialConversion;
use App\Models\SocialEngagement;
use App\Models\SocialLead;
use App\Models\SocialMessage;
use App\Models\SocialPage;
use App\Models\SocialPost;
use App\Models\SocialReview;
use App\Models\SocialSentimentScore;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\WorkflowConnection;
use App\Models\WorkflowExecution;
use App\Models\WorkflowNode;
use App\Policies\AgentApprovalPolicy;
use App\Policies\AgentCategoryPolicy;
use App\Policies\AgentDepartmentPolicy;
use App\Policies\AgentDeploymentPolicy;
use App\Policies\AgentMemoryPolicy;
use App\Policies\AgentMessagePolicy;
use App\Policies\AgentPersonaPolicy;
use App\Policies\AgentPluginInstallationPolicy;
use App\Policies\AgentPluginPolicy;
use App\Policies\AgentPolicy;
use App\Policies\AgentReviewPolicy;
use App\Policies\AgentRunPolicy;
use App\Policies\AgentScorecardPolicy;
use App\Policies\AgentSessionPolicy;
use App\Policies\AgentSkillApprovalPolicy;
use App\Policies\AgentSkillAssignmentPolicy;
use App\Policies\AgentSkillAuditPolicy;
use App\Policies\AgentSkillExecutionPolicy;
use App\Policies\AgentSkillPermissionPolicy;
use App\Policies\AgentSkillPolicy;
use App\Policies\AgentSkillRequirementPolicy;
use App\Policies\AgentSkillScorePolicy;
use App\Policies\AgentTaskPolicy;
use App\Policies\AgentToolPermissionPolicy;
use App\Policies\AgentVersionPolicy;
use App\Policies\AgentWorkflowPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\DecisionLogPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DivisionPolicy;
use App\Policies\EnterpriseDecisionPolicy;
use App\Policies\EnterpriseHealthScorePolicy;
use App\Policies\ExecutiveCouncilSessionPolicy;
use App\Policies\ExecutiveRecommendationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\KnowledgeBasePolicy;
use App\Policies\MembershipPolicy;
use App\Policies\OrganizationDNAPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\OrganizationSocialCredentialPolicy;
use App\Policies\OrganizationSubscriptionPolicy;
use App\Policies\OrganizationTwinPolicy;
use App\Policies\PlatformMegaScorecardPolicy;
use App\Policies\PlatformNotificationPolicy;
use App\Policies\SecurityEventPolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\SocialCampaignPolicy;
use App\Policies\SocialConnectionSettingsPolicy;
use App\Policies\SocialConversationPolicy;
use App\Policies\SocialConversionPolicy;
use App\Policies\SocialEngagementPolicy;
use App\Policies\SocialLeadPolicy;
use App\Policies\SocialMessagePolicy;
use App\Policies\SocialPagePolicy;
use App\Policies\SocialPostPolicy;
use App\Policies\SocialReviewPolicy;
use App\Policies\SocialSentimentScorePolicy;
use App\Policies\SubscriptionPlanPolicy;
use App\Policies\TeamInvitationPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UsageRecordPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookDeliveryPolicy;
use App\Policies\WebhookPolicy;
use App\Policies\WorkflowConnectionPolicy;
use App\Policies\WorkflowExecutionPolicy;
use App\Policies\WorkflowNodePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * PolicyServiceProvider
 *
 * Central registry for all model-to-policy mappings.
 * Extracted from AppServiceProvider to enforce the Single Responsibility
 * Principle and eliminate merge conflicts on a frequently-updated file.
 *
 * Domains:
 *  - AI Agent runtime (Agent, Deployment, Task, Workflow, Session, Skills)
 *  - Governance (AuditLog, DecisionLog, SecurityEvent, Scorecard)
 *  - Organization (Organization, Team, User, Division, Department)
 *  - Knowledge (KnowledgeBase, KnowledgeArticle, Memory)
 *  - Social Commerce (SocialPost, SocialCampaign, etc.)
 *  - Platform Catalogue (AgentCategory, AgentPlugin, SubscriptionPlan)
 *  - Billing (Invoice, UsageRecord, OrganizationSubscription)
 *  - Integration (Webhook, WorkflowConnection, WorkflowNode)
 */
class PolicyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Intentionally empty — policy bindings are registered in boot().
    }

    public function boot(): void
    {
        // ── AI Agent Runtime ────────────────────────────────────────────────
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(AgentDeployment::class, AgentDeploymentPolicy::class);
        Gate::policy(AgentApproval::class, AgentApprovalPolicy::class);
        Gate::policy(AgentTask::class, AgentTaskPolicy::class);
        Gate::policy(AgentRun::class, AgentRunPolicy::class);

        // A person's own data is theirs alone to take or erase.
        Gate::define('export-own-data', fn (User $user, User $requester, User $subject) => $user->is($requester) && $requester->is($subject));
        Gate::define('erase-own-data', fn (User $user, User $requester, User $subject) => $user->is($requester) && $requester->is($subject));

        Gate::policy(AgentWorkflow::class, AgentWorkflowPolicy::class);
        Gate::policy(AgentScorecard::class, AgentScorecardPolicy::class);
        Gate::policy(AgentSession::class, AgentSessionPolicy::class);
        Gate::policy(AgentMessage::class, AgentMessagePolicy::class);
        Gate::policy(AgentReview::class, AgentReviewPolicy::class);
        Gate::policy(AgentVersion::class, AgentVersionPolicy::class);

        // ── Agent Skills ────────────────────────────────────────────────────
        Gate::policy(AgentSkill::class, AgentSkillPolicy::class);
        Gate::policy(AgentSkillApproval::class, AgentSkillApprovalPolicy::class);
        Gate::policy(AgentSkillAssignment::class, AgentSkillAssignmentPolicy::class);
        Gate::policy(AgentSkillAudit::class, AgentSkillAuditPolicy::class);
        Gate::policy(AgentSkillExecution::class, AgentSkillExecutionPolicy::class);
        Gate::policy(AgentSkillPermission::class, AgentSkillPermissionPolicy::class);
        Gate::policy(AgentSkillRequirement::class, AgentSkillRequirementPolicy::class);
        Gate::policy(AgentSkillScore::class, AgentSkillScorePolicy::class);
        Gate::policy(AgentToolPermission::class, AgentToolPermissionPolicy::class);

        // ── Agent Memory & Knowledge ────────────────────────────────────────
        Gate::policy(AgentMemory::class, AgentMemoryPolicy::class);
        Gate::policy(KnowledgeBase::class, KnowledgeBasePolicy::class);
        Gate::policy(KnowledgeArticle::class, KnowledgeArticlePolicy::class);

        // ── Agent Plugins ───────────────────────────────────────────────────
        Gate::policy(AgentPlugin::class, AgentPluginPolicy::class);
        Gate::policy(AgentPluginInstallation::class, AgentPluginInstallationPolicy::class);

        // ── Governance ──────────────────────────────────────────────────────
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(DecisionLog::class, DecisionLogPolicy::class);
        Gate::policy(SecurityEvent::class, SecurityEventPolicy::class);
        Gate::policy(EnterpriseDecision::class, EnterpriseDecisionPolicy::class);
        Gate::policy(EnterpriseHealthScore::class, EnterpriseHealthScorePolicy::class);
        Gate::policy(ExecutiveCouncilSession::class, ExecutiveCouncilSessionPolicy::class);
        Gate::policy(ExecutiveRecommendation::class, ExecutiveRecommendationPolicy::class);
        Gate::policy(PlatformMegaScorecard::class, PlatformMegaScorecardPolicy::class);
        Gate::policy(PlatformNotification::class, PlatformNotificationPolicy::class);

        // ── Organization ────────────────────────────────────────────────────
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(TeamInvitation::class, TeamInvitationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Membership::class, MembershipPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Division::class, DivisionPolicy::class);
        Gate::policy(OrganizationDNA::class, OrganizationDNAPolicy::class);
        Gate::policy(OrganizationSocialCredential::class, OrganizationSocialCredentialPolicy::class);
        Gate::policy(OrganizationSubscription::class, OrganizationSubscriptionPolicy::class);
        Gate::policy(OrganizationTwin::class, OrganizationTwinPolicy::class);

        // ── Billing ─────────────────────────────────────────────────────────
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(UsageRecord::class, UsageRecordPolicy::class);

        // ── Social Commerce & Customer Success ──────────────────────────────
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(SocialCampaign::class, SocialCampaignPolicy::class);
        Gate::policy(SocialConnectionSettings::class, SocialConnectionSettingsPolicy::class);
        Gate::policy(SocialConversation::class, SocialConversationPolicy::class);
        Gate::policy(SocialEngagement::class, SocialEngagementPolicy::class);
        Gate::policy(SocialLead::class, SocialLeadPolicy::class);
        Gate::policy(SocialMessage::class, SocialMessagePolicy::class);
        Gate::policy(SocialPage::class, SocialPagePolicy::class);
        Gate::policy(SocialPost::class, SocialPostPolicy::class);
        Gate::policy(SocialReview::class, SocialReviewPolicy::class);
        Gate::policy(SocialSentimentScore::class, SocialSentimentScorePolicy::class);
        Gate::policy(SocialConversion::class, SocialConversionPolicy::class);

        // ── Platform Catalogue (super-admin managed) ────────────────────────
        Gate::policy(AgentCategory::class, AgentCategoryPolicy::class);
        Gate::policy(AgentDepartment::class, AgentDepartmentPolicy::class);
        Gate::policy(AgentPersona::class, AgentPersonaPolicy::class);
        Gate::policy(SubscriptionPlan::class, SubscriptionPlanPolicy::class);

        // ── Integration / Webhooks ──────────────────────────────────────────
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(WebhookDelivery::class, WebhookDeliveryPolicy::class);
        Gate::policy(WorkflowExecution::class, WorkflowExecutionPolicy::class);
        Gate::policy(WorkflowConnection::class, WorkflowConnectionPolicy::class);
        Gate::policy(WorkflowNode::class, WorkflowNodePolicy::class);
    }
}
