<?php

namespace App\Services\Governance\Financial;

use App\Models\AgentScorecard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FinancialTrendAnalyzer
 *
 * Computes monthly cost/savings trends for Financial Intelligence scoring.
 *
 * Extracted from FinancialIntelligenceService.
 */
class FinancialTrendAnalyzer
{
    private const LOOKBACK_MONTHS = 3;

    /**
     * Whitelist of DB-driver-aware date expressions for month grouping.
     * Values are static SQL literals — never derived from user input.
     */
    private const DATE_EXPR_BY_DRIVER = [
        'sqlite' => "strftime('%Y-%m', period_end)",
        'mysql' => "DATE_FORMAT(period_end, '%Y-%m')",
        'pgsql' => "TO_CHAR(period_end, 'YYYY-MM')",
    ];

    /**
     * Build a monthly cost/savings trend array for the given deployment IDs.
     * Groups AgentScorecard records by month over the last N months.
     *
     * @return array<array{month: string, cost: float, savings: float, records: int}>
     */
    public function compute(Collection $deploymentIds): array
    {
        if ($deploymentIds->isEmpty()) {
            return [];
        }

        $driver = DB::getDriverName();
        $dateExpr = self::DATE_EXPR_BY_DRIVER[$driver]
            ?? throw new \RuntimeException("Unsupported DB driver '{$driver}' for FinancialTrendAnalyzer.");

        $rows = AgentScorecard::withoutGlobalScope('organization')
            ->whereHas('deployment', fn ($q) => $q->whereIn('id', $deploymentIds))
            ->where('period_end', '>=', now()->subMonths(self::LOOKBACK_MONTHS))
            ->selectRaw("{$dateExpr} as month, SUM(total_cost) as cost, SUM(estimated_savings) as savings, COUNT(*) as records")
            ->groupByRaw($dateExpr)
            ->orderByRaw($dateExpr)
            ->get();

        return $rows->map(fn ($r) => [
            'month' => $r->month,
            'cost' => (float) $r->cost,
            'savings' => (float) $r->savings,
            'records' => (int) $r->records,
        ])->toArray();
    }
}
