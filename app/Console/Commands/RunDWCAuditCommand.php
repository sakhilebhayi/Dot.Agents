<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Governance\DWCAAuditService;
use App\Support\TenantContext;
use Illuminate\Console\Command;

/**
 * DWCA Artisan Command
 *
 * Runs the Digital Workforce Certification Audit for all organizations
 * or a single specified organization.
 *
 * Usage:
 *   php artisan dwca:audit               — audit all organizations
 *   php artisan dwca:audit --org=5       — audit organization #5
 *   php artisan dwca:audit --verbose     — verbose output with per-agent details
 */
class RunDWCAuditCommand extends Command
{
    protected $signature = 'dwca:audit
                            {--org= : Organization ID to audit (omit for all organizations)}
                            {--format=table : Output format: table|json}';

    protected $description = 'Run the Digital Workforce Certification Audit (DWCA v1.0) for agent deployments';

    public function handle(DWCAAuditService $auditService): int
    {
        $orgId = $this->option('org');
        $format = $this->option('format');

        if ($orgId) {
            $organizations = Organization::where('id', $orgId)->get();
        } else {
            $organizations = Organization::all();
        }

        if ($organizations->isEmpty()) {
            $this->error('No organizations found.');

            return self::FAILURE;
        }

        $this->info("[DWCA v1.0] Starting Digital Workforce Certification Audit for {$organizations->count()} organization(s)...");

        $allReports = [];

        foreach ($organizations as $org) {
            $this->line("  → Auditing: {$org->name} (ID: {$org->id})");

            try {
                $report = TenantContext::forOrganization($org->id, fn () => $auditService->auditOrganization($org->id));
                $allReports[] = ['org' => $org->name, 'report' => $report];

                if ($format === 'table') {
                    $this->renderTable($org->name, $report);
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed for {$org->name}: {$e->getMessage()}");
            }
        }

        if ($format === 'json') {
            $this->line(json_encode($allReports, JSON_PRETTY_PRINT));
        }

        $this->newLine();
        $this->info('[DWCA v1.0] Audit complete.');

        return self::SUCCESS;
    }

    private function renderTable(string $orgName, array $report): void
    {
        $this->newLine();
        $this->line("━━━ Organization: {$orgName} ━━━");
        $this->line("Enterprise Certification: [{$report['certification_label']}]  Score: {$report['composite_score']}/100");
        $this->newLine();

        $rows = [];
        foreach ($report['agent_results'] ?? [] as $result) {
            $rows[] = [
                $result['agent_name'],
                $result['composite_score'].'/100',
                $result['certification_label'],
                $result['maturity_label'].' (L'.$result['maturity_level'].')',
                $result['marketplace_eligible'] ? '✓ Eligible' : '✗ Blocked',
                count($result['failures'] ?? []) > 0 ? implode(', ', array_slice($result['failures'], 0, 2)) : 'None',
            ];
        }

        $this->table(
            ['Agent', 'Score', 'Certification', 'Maturity', 'Marketplace', 'Failures (first 2)'],
            $rows
        );

        if (! empty($report['remediation_roadmap'])) {
            $this->newLine();
            $this->line('  Remediation Roadmap (top 5):');
            foreach (array_slice($report['remediation_roadmap'], 0, 5) as $item) {
                $this->line("  [{$item['priority']}] {$item['agent']}: {$item['finding']}");
                $this->line("       → {$item['recommendation']}");
            }
        }
    }
}
