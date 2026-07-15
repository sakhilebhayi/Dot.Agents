<?php

namespace App\Actions\Social;

use App\Events\LeadQualified;
use App\Models\SocialLead;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Gate;

class QualifyLeadAction
{
    // Intent level thresholds
    private const INTENT_THRESHOLDS = [
        'high_intent' => 90,
        'ready_to_buy' => 75,
        'considering' => 50,
        'interested' => 25,
        'browsing' => 0,
    ];

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function execute(SocialLead $lead, int $actorId): SocialLead
    {
        Gate::authorize('update', $lead);

        $intentLevel = $this->resolveIntentLevel($lead->intent_score);
        $priority = $this->resolvePriority($lead->lead_score);
        $recommendedActions = $this->buildRecommendedActions($lead, $intentLevel);

        $lead->update([
            'status' => 'qualified',
            'intent_level' => $intentLevel,
            'priority' => $priority,
            'recommended_actions' => $recommendedActions,
            'qualified_at' => now(),
        ]);

        $this->auditService->logUserAction(
            event: 'social_lead.qualified',
            description: "Lead qualified with intent level: {$intentLevel}",
            data: [
                'intent_level' => $intentLevel,
                'lead_score' => $lead->lead_score,
                'recommended_actions' => $recommendedActions,
            ],
            subject: $lead,
        );

        event(new LeadQualified($lead->fresh(), $intentLevel));

        return $lead->fresh();
    }

    private function resolveIntentLevel(float $intentScore): string
    {
        foreach (self::INTENT_THRESHOLDS as $level => $threshold) {
            if ($intentScore >= $threshold) {
                return $level;
            }
        }

        return 'browsing';
    }

    private function resolvePriority(float $leadScore): string
    {
        return match (true) {
            $leadScore >= 85 => 'hot',
            $leadScore >= 65 => 'high',
            $leadScore >= 40 => 'normal',
            default => 'low',
        };
    }

    private function buildRecommendedActions(SocialLead $lead, string $intentLevel): array
    {
        $actions = [];

        if (in_array($intentLevel, ['high_intent', 'ready_to_buy'])) {
            $actions[] = 'transfer_to_sales';
            $actions[] = 'book_demo';
        }

        if ($intentLevel === 'considering') {
            $actions[] = 'offer_discount';
            $actions[] = 'send_case_study';
        }

        if ($lead->lead_score >= 70) {
            $actions[] = 'generate_crm_record';
        }

        if ($intentLevel === 'interested') {
            $actions[] = 'send_product_info';
            $actions[] = 'schedule_follow_up';
        }

        return $actions;
    }
}
