<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\EnterpriseBrainService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Constructor-signature guard for EnterpriseBrainService.
 *
 * AuditService and ScorecardService were previously injected but never read
 * anywhere in the class body (phpstan property.onlyWritten) — the 6
 * intelligence-core methods are read-only assessments that neither write
 * audit trail entries nor recompute scorecards. This test locks the
 * constructor down to the dependencies the class actually uses.
 */
class EnterpriseBrainServiceTest extends TestCase
{
    public function test_constructor_only_declares_dependencies_actually_used(): void
    {
        $constructor = new ReflectionMethod(EnterpriseBrainService::class, '__construct');
        $paramNames = array_map(fn ($p) => $p->getName(), $constructor->getParameters());

        $this->assertSame(['constitutionService', 'scorer'], $paramNames);
    }

    public function test_service_still_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(EnterpriseBrainService::class, app(EnterpriseBrainService::class));
    }
}
