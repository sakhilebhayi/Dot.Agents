<?php

use App\Providers\AgentEventServiceProvider;
use App\Providers\AgentServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BillingEventServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\GovernanceEventServiceProvider;
use App\Providers\GovernanceServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\JetstreamServiceProvider;
use App\Providers\PolicyServiceProvider;
use App\Providers\SocialServiceProvider;

return [
    AppServiceProvider::class,
    PolicyServiceProvider::class,
    AgentServiceProvider::class,
    GovernanceServiceProvider::class,
    SocialServiceProvider::class,
    // Root event provider (Socialite, social/SCCS, org lifecycle)
    EventServiceProvider::class,
    // Domain event providers
    AgentEventServiceProvider::class,
    GovernanceEventServiceProvider::class,
    BillingEventServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    JetstreamServiceProvider::class,
];
