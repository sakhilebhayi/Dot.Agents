<?php

namespace App\Providers;

use App\Events\ApiTokenRevoked;
use App\Events\ConnectionSettingsSaved;
use App\Events\ConversationEscalated;
use App\Events\DepartmentDeleted;
use App\Events\DepartmentSaved;
use App\Events\EscalationHandled;
use App\Events\KnowledgeArticleDeleted;
use App\Events\KnowledgeArticleSaved;
use App\Events\KnowledgeBaseCreated;
use App\Events\LeadQualified;
use App\Events\NegativeSentimentDetected;
use App\Events\PurchaseIntentDetected;
use App\Events\SocialAccountConnected;
use App\Events\SocialAccountDisconnected;
use App\Events\SocialConversionAchieved;
use App\Events\SocialLeadCaptured;
use App\Events\SocialMessageReceived;
use App\Events\SocialPostApproved;
use App\Events\SocialPostPublished;
use App\Events\SocialPostScheduled;
use App\Listeners\LogOrganizationLifecycleEvents;
use App\Listeners\LogPurchaseIntentDetected;
use App\Listeners\LogSocialConversionAchieved;
use App\Listeners\LogSocialLeadCaptured;
use App\Listeners\LogSocialLifecycleEvents;
use App\Listeners\LogSocialMessageReceived;
use App\Listeners\LogSocialPostPublished;
use App\Listeners\NotifyOnNegativeSentiment;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Patreon\PatreonExtendSocialite;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use SocialiteProviders\Reddit\RedditExtendSocialite;
use SocialiteProviders\Snapchat\SnapchatExtendSocialite;
use SocialiteProviders\Twitch\TwitchExtendSocialite;
use SocialiteProviders\YouTube\YouTubeExtendSocialite;

/**
 * Root event service provider.
 *
 * Owns Socialite community drivers, social/SCCS events, and organization
 * lifecycle events. Domain-specific events are split into:
 *
 *   - AgentEventServiceProvider      — agents, skills, workflows, approvals
 *   - GovernanceEventServiceProvider — security, compliance, org settings
 *   - BillingEventServiceProvider    — subscriptions, checkout, credentials
 *
 * Auto-discovery is intentionally disabled; the explicit $listen maps
 * across all four providers are the single source of truth for all bindings.
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ── SocialiteProviders community drivers ────────────────────────────
        SocialiteWasCalled::class => [
            YouTubeExtendSocialite::class.'@handle',
            PinterestExtendSocialite::class.'@handle',
            PatreonExtendSocialite::class.'@handle',
            SnapchatExtendSocialite::class.'@handle',
            RedditExtendSocialite::class.'@handle',
            DiscordExtendSocialite::class.'@handle',
            TwitchExtendSocialite::class.'@handle',
        ],

        // ── SCCS: Social Commerce & Customer Success events ──────────────────
        SocialLeadCaptured::class => [LogSocialLeadCaptured::class],
        SocialConversionAchieved::class => [LogSocialConversionAchieved::class],
        NegativeSentimentDetected::class => [NotifyOnNegativeSentiment::class],
        PurchaseIntentDetected::class => [LogPurchaseIntentDetected::class],
        SocialMessageReceived::class => [LogSocialMessageReceived::class],
        SocialPostPublished::class => [LogSocialPostPublished::class],

        // ── Social lifecycle events ──────────────────────────────────────────
        SocialAccountConnected::class => [LogSocialLifecycleEvents::class.'@handleSocialAccountConnected'],
        SocialAccountDisconnected::class => [LogSocialLifecycleEvents::class.'@handleSocialAccountDisconnected'],
        SocialPostApproved::class => [LogSocialLifecycleEvents::class.'@handleSocialPostApproved'],
        SocialPostScheduled::class => [LogSocialLifecycleEvents::class.'@handleSocialPostScheduled'],
        ConversationEscalated::class => [LogSocialLifecycleEvents::class.'@handleConversationEscalated'],
        EscalationHandled::class => [LogSocialLifecycleEvents::class.'@handleEscalationHandled'],
        LeadQualified::class => [LogSocialLifecycleEvents::class.'@handleLeadQualified'],

        // ── Organization lifecycle events ─────────────────────────────────────
        DepartmentSaved::class => [LogOrganizationLifecycleEvents::class.'@handleDepartmentSaved'],
        DepartmentDeleted::class => [LogOrganizationLifecycleEvents::class.'@handleDepartmentDeleted'],
        KnowledgeBaseCreated::class => [LogOrganizationLifecycleEvents::class.'@handleKnowledgeBaseCreated'],
        KnowledgeArticleSaved::class => [LogOrganizationLifecycleEvents::class.'@handleKnowledgeArticleSaved'],
        KnowledgeArticleDeleted::class => [LogOrganizationLifecycleEvents::class.'@handleKnowledgeArticleDeleted'],
        ConnectionSettingsSaved::class => [LogOrganizationLifecycleEvents::class.'@handleConnectionSettingsSaved'],
        ApiTokenRevoked::class => [LogOrganizationLifecycleEvents::class.'@handleApiTokenRevoked'],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
