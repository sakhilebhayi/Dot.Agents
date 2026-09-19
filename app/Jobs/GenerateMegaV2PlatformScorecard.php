<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\PlatformMegaScorecard;
use App\Services\Governance\MegaV2ScorecardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateMegaV2PlatformScorecard
 *
 * Generates the MEGA V2 Autonomous Enterprise Readiness Scorecard for all
 * active organizations and persists each result to platform_mega_scorecards.
 *
 * Scheduled: daily at 03:00 on one server.
 */
class GenerateMegaV2PlatformScorecard implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private readonly ?int $organizationId = null,
    ) {}

    /**
     * Prevent concurrent runs for the same organization.
     */
    public function middleware(): array
    {
        $suffix = $this->organizationId ?? 'all';

        return [new WithoutOverlapping("mega-v2-scorecard-{$suffix}", expiresAfter: 120)];
    }

    public function handle(MegaV2ScorecardService $service): void
    {
        $orgs = $this->organizationId
            ? Organization::where('id', $this->organizationId)->where('status', '!=', 'suspended')->get()
            : Organization::where('status', '!=', 'suspended')->get();

        foreach ($orgs as $org) {
            $this->generateForOrg($service, $org);
        }
    }

    private function generateForOrg(MegaV2ScorecardService $service, Organization $org): void
    {
        try {
            // Invalidate cached score to force a fresh computation
            $service->invalidate($org);

            $result = $service->generate($org);

            $technical = $result['breakdown']['technical']['domains'];
            $intelligence = $result['breakdown']['intelligence']['domains'];
            $business = $result['breakdown']['business']['domains'];
            $sources = $result['source_scores'];

            PlatformMegaScorecard::create([
                'organization_id' => $org->id,
                'final_score' => $result['final_score'],
                'certification' => $result['certification'],
                'level' => $result['level'],
                'gate_pass' => $result['gate_pass'],

                'security_score' => $technical['security_cyber_defense']['score'],
                'compliance_score' => $technical['compliance_governance']['score'],
                'architecture_score' => $technical['architecture_code_quality']['score'],
                'infrastructure_score' => $technical['infrastructure_devops']['score'],
                'data_engineering_score' => $technical['database_data_engineering']['score'],
                'performance_score' => $technical['performance_scalability']['score'],
                'api_score' => $technical['api_integration']['score'],
                'testing_score' => $technical['testing_qa']['score'],
                'observability_score' => $technical['monitoring_observability']['score'],
                'communication_score' => $technical['email_communication']['score'],

                'ai_governance_score' => $intelligence['ai_governance']['score'],
                'ai_accuracy_score' => $intelligence['ai_accuracy_prediction']['score'],
                'ai_drift_score' => $intelligence['ai_drift_control']['score'],
                'agent_reliability_score' => $intelligence['agent_reliability']['score'],
                'agent_collaboration_score' => $intelligence['agent_collaboration']['score'],
                'reality_alignment_score' => $intelligence['reality_alignment']['score'],
                'hallucination_resistance_score' => $intelligence['hallucination_resistance']['score'],
                'decision_intelligence_score' => $intelligence['decision_intelligence']['score'],

                'customer_success_score' => $business['customer_success']['score'],
                'operational_efficiency_score' => $business['operational_efficiency']['score'],
                'financial_intelligence_score' => $business['financial_intelligence']['score'],
                'product_strategy_score' => $business['product_strategy']['score'],
                'innovation_score' => $business['innovation_capacity']['score'],

                'data_trust_score' => $sources['data_trust'],
                'prediction_accuracy_score' => $sources['prediction_accuracy'],
                'org_memory_score' => $sources['org_memory'],

                'gate_details' => $result['gates'],
                'full_breakdown' => $result['breakdown'],
            ]);

            Log::info('[MegaV2] Scorecard generated', [
                'organization_id' => $org->id,
                'score' => $result['final_score'],
                'certification' => $result['certification'],
                'gate_pass' => $result['gate_pass'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[MegaV2] Scorecard generation failed', [
                'organization_id' => $org->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
