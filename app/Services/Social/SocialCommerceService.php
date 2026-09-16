<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialConversion;
use App\Models\SocialLead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Social Commerce Service.
 *
 * Aggregate read service for the SCCS dashboard — provides metrics,
 * scorecard data, and operational status across all social channels
 * for an organization.
 */
class SocialCommerceService
{
    /**
     * Returns the Customer Success Scorecard for an organization.
     *
     * @return array{
     *   response_time_avg_seconds: float,
     *   engagement_rate_avg: float,
     *   lead_conversion_rate: float,
     *   active_leads: int,
     *   hot_leads: int,
     *   total_conversions: int,
     *   total_revenue: float,
     *   upsell_conversions: int,
     *   sentiment_health_score: float,
     *   connected_accounts: int,
     * }
     */
    public function getScorecard(int $organizationId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $responseTimeAvg = DB::table('social_conversations')
            ->where('organization_id', $organizationId)
            ->whereNotNull('response_time_seconds')
            ->where('created_at', '>=', $since)
            ->avg('response_time_seconds') ?? 0;

        $engagementRateAvg = DB::table('social_pages')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->avg('engagement_rate') ?? 0;

        $totalLeads = SocialLead::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('first_touch_at', '>=', $since)
            ->count();

        $convertedLeads = SocialLead::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('status', 'converted')
            ->where('converted_at', '>=', $since)
            ->count();

        $activeLeads = SocialLead::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->count();

        $hotLeads = SocialLead::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->where('priority', 'hot')->orWhere('lead_score', '>=', 80))
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->count();

        $conversionMetrics = SocialConversion::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('converted_at', '>=', $since)
            ->selectRaw("COUNT(*) as total, SUM(revenue) as total_revenue, SUM(CASE WHEN conversion_type = 'upsell' THEN 1 ELSE 0 END) as upsell_count")
            ->first();

        $sentimentAvg = DB::table('social_sentiment_scores')
            ->where('organization_id', $organizationId)
            ->where('scored_at', '>=', $since)
            ->avg('score') ?? 50;

        $connectedAccounts = SocialAccount::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->count();

        return [
            'response_time_avg_seconds' => round((float) $responseTimeAvg, 1),
            'engagement_rate_avg' => round((float) $engagementRateAvg, 2),
            'lead_conversion_rate' => $totalLeads > 0 ? round($convertedLeads / $totalLeads * 100, 2) : 0.0,
            'active_leads' => $activeLeads,
            'hot_leads' => $hotLeads,
            'total_conversions' => (int) ($conversionMetrics->total ?? 0),
            'total_revenue' => round((float) ($conversionMetrics->total_revenue ?? 0), 2),
            'upsell_conversions' => (int) ($conversionMetrics->upsell_count ?? 0),
            'sentiment_health_score' => round((float) $sentimentAvg, 2),
            'connected_accounts' => $connectedAccounts,
        ];
    }

    /**
     * Returns conversations that need immediate human attention.
     */
    public function getUrgentConversations(int $organizationId): Collection
    {
        return SocialConversation::withoutGlobalScope('organization')
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q
                ->where('requires_human', true)
                ->orWhere('priority', 'urgent')
                ->orWhereIn('sentiment', ['frustrated', 'angry'])
            )
            ->whereNotIn('status', ['resolved', 'closed'])
            ->with(['socialAccount', 'agentDeployment'])
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get();
    }
}
