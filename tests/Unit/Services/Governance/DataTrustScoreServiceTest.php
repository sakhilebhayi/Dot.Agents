<?php

namespace Tests\Unit\Services\Governance;

use App\Services\Governance\DataTrustScoreService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Constructor-signature guard for DataTrustScoreService.
 *
 * AuditService was previously injected but never read anywhere in the class
 * body (phpstan property.onlyWritten). This is a thin scoring coordinator —
 * it already reports its below-gate condition via Log::warning, matching the
 * convention used by every other gate-scoring service in this codebase
 * (AgentReliabilityAuditorService, PredictionAccuracyTrackingService, etc.),
 * none of which inject AuditService either. This test locks the constructor
 * down to the dependencies the class actually uses.
 */
class DataTrustScoreServiceTest extends TestCase
{
    public function test_constructor_only_declares_dependencies_actually_used(): void
    {
        $constructor = new ReflectionMethod(DataTrustScoreService::class, '__construct');
        $paramNames = array_map(fn ($p) => $p->getName(), $constructor->getParameters());

        $this->assertSame(['qualityScorer', 'governanceScorer'], $paramNames);
    }

    public function test_service_still_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(DataTrustScoreService::class, app(DataTrustScoreService::class));
    }
}
