<?php

namespace App\Providers;

use App\Events\AuditLogExported;
use App\Events\ConsentRecorded;
use App\Events\DecisionLogCreated;
use App\Events\KillSwitchActivated;
use App\Events\OrganizationCreated;
use App\Events\OrganizationSettingsUpdated;
use App\Events\PluginInstalled;
use App\Events\PluginUninstalled;
use App\Events\SecurityEventResolved;
use App\Events\SecurityThreatDetected;
use App\Events\UserDataErased;
use App\Events\UserDataExported;
use App\Listeners\LogAuditLogExported;
use App\Listeners\LogComplianceAuditEvents;
use App\Listeners\LogKillSwitchActivated;
use App\Listeners\LogMarketplaceAndGovernanceEvents;
use App\Listeners\LogOrganizationSettingsUpdated;
use App\Listeners\LogSecurityEventResolved;
use App\Listeners\LogSecurityThreat;
use App\Listeners\SetupOrganizationDefaults;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Security, governance, compliance, and organization event bindings.
 */
class GovernanceEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SecurityThreatDetected::class => [
            LogSecurityThreat::class,
        ],

        SecurityEventResolved::class => [
            LogSecurityEventResolved::class,
        ],

        KillSwitchActivated::class => [
            LogKillSwitchActivated::class,
        ],

        OrganizationCreated::class => [
            SetupOrganizationDefaults::class,
        ],

        OrganizationSettingsUpdated::class => [
            LogOrganizationSettingsUpdated::class,
        ],

        AuditLogExported::class => [
            LogAuditLogExported::class,
        ],

        UserDataErased::class => [
            LogComplianceAuditEvents::class.'@handleUserDataErased',
        ],

        ConsentRecorded::class => [
            LogComplianceAuditEvents::class.'@handleConsentRecorded',
        ],

        UserDataExported::class => [
            LogComplianceAuditEvents::class.'@handleUserDataExported',
        ],

        PluginInstalled::class => [
            LogMarketplaceAndGovernanceEvents::class.'@handlePluginInstalled',
        ],

        PluginUninstalled::class => [
            LogMarketplaceAndGovernanceEvents::class.'@handlePluginUninstalled',
        ],

        DecisionLogCreated::class => [
            LogMarketplaceAndGovernanceEvents::class.'@handleDecisionLogCreated',
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
