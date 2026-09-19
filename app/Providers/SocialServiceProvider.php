<?php

namespace App\Providers;

use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use App\Services\Infrastructure\ObservabilityService;
use App\Services\Resilience\CircuitBreakerService;
use App\Services\Social\ContinuationContentGenerator;
use App\Services\Social\ConversationContinuationService;
use App\Services\Social\LeadQualificationService;
use App\Services\Social\ReputationMonitoringService;
use App\Services\Social\SentimentAnalysisService;
use App\Services\Social\SocialCommerceService;
use App\Services\Social\SocialPublishingService;
use Illuminate\Support\ServiceProvider;

class SocialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ObservabilityService::class);
        $this->app->singleton(CircuitBreakerService::class);

        $this->app->singleton(SentimentAnalysisService::class);
        $this->app->singleton(LeadQualificationService::class);
        $this->app->singleton(SocialPublishingService::class);
        $this->app->singleton(SocialCommerceService::class);

        $this->app->singleton(ReputationMonitoringService::class, function ($app) {
            return new ReputationMonitoringService(
                $app->make(SentimentAnalysisService::class),
            );
        });

        $this->app->singleton(ConversationContinuationService::class, function ($app) {
            return new ConversationContinuationService(
                $app->make(AuditService::class),
                $app->make(DelusionDetectionService::class),
                $app->make(ContinuationContentGenerator::class),
            );
        });
    }
}
