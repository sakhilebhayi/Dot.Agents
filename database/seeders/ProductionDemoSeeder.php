<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\AgentTask;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ProductionDemoSeeder
 *
 * Sets up a complete, realistic demo environment so you can test the
 * platform exactly as a real enterprise customer would:
 *
 *   1. One enterprise organization (Dot Ventures Inc.)
 *   2. A test admin user you can log in with immediately
 *   3. Role-specific users (finance lead, HR lead, IT lead, sales lead)
 *   4. Six org departments (Finance, HR, IT, Sales, Marketing, Operations)
 *   5. Every agent from the marketplace deployed and activated per department
 *   6. All 166 enterprise skills assigned to the relevant deployments
 *   7. Sample agent tasks (pending / completed / failed) per deployment
 *   8. The organization upgraded to the Enterprise plan (no limits)
 *
 * Run:
 *   php artisan db:seed --class=ProductionDemoSeeder
 *
 * Login:
 *   Email   : demo@dotagents.com
 *   Password: DemoAdmin2026!
 */
class ProductionDemoSeeder extends Seeder
{
    // ─────────────────────────────────────────────────────────────────────────
    // Entry point
    // ─────────────────────────────────────────────────────────────────────────

    public function run(): void
    {
        // These accounts have passwords in a public repository.
        abort_if(app()->isProduction(), 500, 'Demo accounts are never seeded in production. Seed the catalogue with AgentPlatformSeeder.');

        $this->command->info('▶  ProductionDemoSeeder starting …');

        // Admin user must exist before org (owner_id FK constraint)
        $admin = $this->ensureAdminUser();
        $org = $this->seedOrganization($admin);
        $this->seedRemainingUsers($org, $admin);
        $depts = $this->seedDepartments($org, $admin);
        $this->seedDeployments($org, $admin, $depts);
        $this->upgradeToEnterprise($org);

        $this->command->info('✅  Demo environment ready.');
        $this->command->newLine();
        $this->command->line('  <fg=yellow>Login:</> demo@dotagents.com / DemoAdmin2026!');
        $this->command->line('  <fg=yellow>Org:</>   '.$org->name.' (plan: enterprise)');
        $this->command->line('  <fg=yellow>Agents deployed:</> '.AgentDeployment::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $this->command->line('  <fg=yellow>Tasks seeded:</> '.AgentTask::withoutGlobalScopes()->where('organization_id', $org->id)->count());
        $this->command->newLine();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Admin user (created before org to satisfy FK)
    // ─────────────────────────────────────────────────────────────────────────

    private function ensureAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo@dotagents.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('DemoAdmin2026!'),
                'email_verified_at' => now(),
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Organization
    // ─────────────────────────────────────────────────────────────────────────

    private function seedOrganization(User $admin): Organization
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'dot-ventures'],
            [
                'name' => 'Dot Ventures Inc.',
                'owner_id' => $admin->id,
                'domain' => 'dotventures.com',
                'industry' => 'Technology',
                'size' => '201-500',
                'country' => 'US',
                'timezone' => 'America/New_York',
                'currency' => 'USD',
                'plan' => 'enterprise',
                'status' => 'active',
                'settings' => [
                    'max_skill_risk_level' => 'critical',
                    'require_2fa' => false,
                    'allow_autonomous_agents' => true,
                    'demo_mode' => true,
                ],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addYear(),
            ]
        );

        // Ensure it has a Jetstream team so session context works
        Team::firstOrCreate(
            ['name' => $org->name],
            [
                'user_id' => $admin->id,
                'personal_team' => false,
            ]
        );

        // Attach admin to org if not already a member
        if (! $org->users()->where('users.id', $admin->id)->exists()) {
            $org->users()->attach($admin->id, [
                'role' => 'owner',
                'department' => 'executive',
                'job_title' => 'Platform Administrator',
                'is_primary' => true,
                'joined_at' => now(),
            ]);
        }

        $this->command->info('  [1/5] Organization: '.$org->name);

        return $org;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Remaining users (non-admin)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedRemainingUsers(Organization $org, User $admin): void
    {
        $usersData = [
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah.chen@dotventures.com',
                'password' => Hash::make('Finance2026!'),
                'role' => 'admin',
                'job_title' => 'Finance Director',
                'department' => 'finance',
            ],
            [
                'name' => 'Marcus Williams',
                'email' => 'marcus.williams@dotventures.com',
                'password' => Hash::make('HRLead2026!'),
                'role' => 'member',
                'job_title' => 'HR Director',
                'department' => 'hr',
            ],
            [
                'name' => 'Priya Sharma',
                'email' => 'priya.sharma@dotventures.com',
                'password' => Hash::make('ITLead2026!'),
                'role' => 'admin',
                'job_title' => 'Head of IT',
                'department' => 'it',
            ],
            [
                'name' => 'James Okafor',
                'email' => 'james.okafor@dotventures.com',
                'password' => Hash::make('SalesLead2026!'),
                'role' => 'member',
                'job_title' => 'VP Sales',
                'department' => 'sales',
            ],
        ];

        foreach ($usersData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                ]
            );

            if (! $org->users()->where('users.id', $user->id)->exists()) {
                $org->users()->attach($user->id, [
                    'role' => $data['role'],
                    'department' => $data['department'],
                    'job_title' => $data['job_title'],
                    'is_primary' => true,
                    'joined_at' => now(),
                ]);
            }
        }

        $this->command->info('  [2/5] Users seeded: '.(count($usersData) + 1).' total');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Org Departments
    // ─────────────────────────────────────────────────────────────────────────

    private function seedDepartments(Organization $org, User $admin): array
    {
        $deptDefs = [
            'finance' => ['name' => 'Finance',         'type' => 'finance',    'budget' => 500000,  'cost_center' => 'CC-FIN-001'],
            'hr' => ['name' => 'Human Resources',  'type' => 'hr',         'budget' => 250000,  'cost_center' => 'CC-HR-001'],
            'it' => ['name' => 'Information Tech', 'type' => 'it',         'budget' => 750000,  'cost_center' => 'CC-IT-001'],
            'sales' => ['name' => 'Sales',            'type' => 'sales',      'budget' => 300000,  'cost_center' => 'CC-SAL-001'],
            'marketing' => ['name' => 'Marketing',        'type' => 'marketing',  'budget' => 200000,  'cost_center' => 'CC-MKT-001'],
            'operations' => ['name' => 'Operations',       'type' => 'operations', 'budget' => 400000,  'cost_center' => 'CC-OPS-001'],
            'executive' => ['name' => 'Executive',        'type' => 'executive',  'budget' => 1000000, 'cost_center' => 'CC-EXEC-001'],
        ];

        $depts = [];

        foreach ($deptDefs as $key => $def) {
            $dept = Department::firstOrCreate(
                ['organization_id' => $org->id, 'slug' => $key],
                [
                    'organization_id' => $org->id,
                    'name' => $def['name'],
                    'slug' => $key,
                    'type' => $def['type'],
                    'budget' => $def['budget'],
                    'cost_center' => $def['cost_center'],
                    'head_user_id' => $admin->id,
                    'is_active' => true,
                ]
            );

            $depts[$key] = $dept;
        }

        $this->command->info('  [3/5] Departments seeded: '.count($depts));

        return $depts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Agent Deployments + Skill Assignments + Tasks
    // ─────────────────────────────────────────────────────────────────────────

    private function seedDeployments(Organization $org, User $admin, array $depts): void
    {
        $deploymentMap = $this->deploymentDefinitions();

        $totalDeployments = 0;
        $totalAssignments = 0;
        $totalTasks = 0;

        foreach ($deploymentMap as $config) {
            $agent = Agent::where('slug', $config['agent_slug'])->first();
            if (! $agent) {
                continue;
            }

            $dept = $depts[$config['department']] ?? null;

            // Create the deployment
            $deployment = AgentDeployment::withoutGlobalScopes()
                ->where('organization_id', $org->id)
                ->where('agent_id', $agent->id)
                ->first();

            if (! $deployment) {
                $deployment = AgentDeployment::create([
                    'uuid' => (string) Str::uuid(),
                    'organization_id' => $org->id,
                    'agent_id' => $agent->id,
                    'department_id' => $dept?->id,
                    'deployed_by' => $admin->id,
                    'name' => $config['name'],
                    'custom_instructions' => $config['instructions'],
                    'deployment_mode' => $config['mode'],
                    'status' => 'active',
                    'requires_human_approval' => $config['mode'] !== 'autonomous',
                    'confidence_threshold' => $config['confidence'] ?? 75.0,
                    'enable_memory' => true,
                    'enable_long_term_memory' => in_array($config['mode'], ['autonomous', 'executive_approval']),
                    'memory_retention_days' => 90,
                    'risk_tolerance' => 50.0,
                    'deployed_at' => now()->subDays(rand(1, 30)),
                    'last_active_at' => now()->subMinutes(rand(1, 480)),
                ]);

                $totalDeployments++;
            }

            // Assign department-matching enterprise skills
            $assignments = $this->assignSkills($deployment, $config['department'], $org->id);
            $totalAssignments += $assignments;

            // Seed sample tasks
            $tasks = $this->seedTasks($deployment, $org, $admin, $config);
            $totalTasks += $tasks;
        }

        $this->command->info("  [4/5] Deployments: {$totalDeployments} created, {$totalAssignments} skill assignments, {$totalTasks} tasks");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Skill Assignment helper
    // ─────────────────────────────────────────────────────────────────────────

    private function assignSkills(AgentDeployment $deployment, string $department, int $orgId): int
    {
        $skills = AgentSkill::active()
            ->where(function ($q) use ($department) {
                $q->where('department', $department)
                    ->orWhere('department', 'platform');
            })
            ->get();

        $count = 0;

        foreach ($skills as $skill) {
            $exists = AgentSkillAssignment::withoutGlobalScopes()
                ->where('agent_deployment_id', $deployment->id)
                ->where('skill_id', $skill->id)
                ->exists();

            if (! $exists) {
                AgentSkillAssignment::create([
                    'agent_deployment_id' => $deployment->id,
                    'skill_id' => $skill->id,
                    'organization_id' => $orgId,
                    'is_enabled' => true,
                    'config' => null,
                ]);
                $count++;
            }
        }

        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task seeding helper
    // ─────────────────────────────────────────────────────────────────────────

    private function seedTasks(AgentDeployment $deployment, Organization $org, User $admin, array $config): int
    {
        $count = 0;

        foreach ($config['sample_tasks'] as $taskDef) {
            $exists = AgentTask::withoutGlobalScopes()
                ->where('agent_deployment_id', $deployment->id)
                ->where('title', $taskDef['title'])
                ->exists();

            if ($exists) {
                continue;
            }

            $isCompleted = $taskDef['status'] === 'completed';
            $isFailed = $taskDef['status'] === 'failed';

            AgentTask::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $org->id,
                'agent_deployment_id' => $deployment->id,
                'assigned_by' => $admin->id,
                'title' => $taskDef['title'],
                'description' => $taskDef['description'],
                'task_type' => $taskDef['type'],
                'priority' => $taskDef['priority'],
                'status' => $taskDef['status'],
                'input_data' => ['query' => $taskDef['description']],
                'output_data' => $isCompleted ? [
                    'summary' => 'Analysis completed successfully. Key findings: '.$taskDef['description'],
                    'confidence' => rand(78, 97),
                    'actions' => [],
                ] : null,
                'result_summary' => $isCompleted ? 'Task completed with high confidence.' : null,
                'confidence_score' => $isCompleted ? rand(78, 97) : null,
                'accuracy_score' => $isCompleted ? rand(80, 98) : null,
                'risk_score' => rand(0, 30),
                'delusion_risk_score' => rand(0, 15),
                'reality_alignment_score' => rand(85, 100),
                'estimated_duration_minutes' => rand(2, 45),
                'actual_duration_minutes' => $isCompleted ? rand(1, 30) : null,
                'token_count' => $isCompleted ? rand(500, 8000) : 0,
                'cost' => $isCompleted ? rand(1, 200) / 100 : 0,
                'due_at' => now()->addHours(rand(1, 72)),
                'started_at' => ($isCompleted || $isFailed) ? now()->subHours(rand(1, 12)) : null,
                'completed_at' => $isCompleted ? now()->subMinutes(rand(5, 300)) : null,
            ]);

            $count++;
        }

        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Upgrade org plan
    // ─────────────────────────────────────────────────────────────────────────

    private function upgradeToEnterprise(Organization $org): void
    {
        $org->update([
            'plan' => 'enterprise',
            'subscription_ends_at' => now()->addYear(),
        ]);

        $this->command->info('  [5/5] Organization upgraded to Enterprise plan');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Deployment definitions — every agent, with real names and tasks
    // ─────────────────────────────────────────────────────────────────────────

    private function deploymentDefinitions(): array
    {
        return [

            // ── EXECUTIVE ────────────────────────────────────────────────────

            [
                'agent_slug' => 'ceo-agent',
                'name' => 'CEO Strategic Advisor',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 80.0,
                'instructions' => 'You are the strategic AI advisor for Dot Ventures Inc. Focus on identifying growth opportunities, monitoring organizational KPIs, and supporting executive decision-making. Always recommend human review for major strategic decisions.',
                'sample_tasks' => [
                    ['title' => 'Q2 Strategic Performance Review', 'description' => 'Analyze Q2 organizational performance across all departments and highlight strategic risks and opportunities.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Board Report: AI Workforce ROI Analysis', 'description' => 'Generate a board-ready report on the ROI of the AI workforce deployment across Dot Ventures.', 'type' => 'report', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Market Expansion Opportunity Assessment', 'description' => 'Evaluate three potential new market expansion opportunities in APAC region.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'in_progress'],
                    ['title' => 'Competitor Intelligence Briefing', 'description' => 'Compile competitive intelligence briefing on top 5 competitors for Q3 strategy planning.', 'type' => 'report', 'priority' => 'medium', 'status' => 'completed'],
                ],
            ],
            [
                'agent_slug' => 'cfo-agent',
                'name' => 'CFO Financial Advisor',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 82.0,
                'instructions' => 'You are the CFO-level AI advisor for Dot Ventures. Focus on financial performance monitoring, budget compliance, cash flow, and financial risk identification. All financial commitments require human CFO approval.',
                'sample_tasks' => [
                    ['title' => 'Monthly Financial Dashboard — June 2026', 'description' => 'Generate monthly financial dashboard including P&L summary, cash flow, and budget variance.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Q3 Budget Forecast', 'description' => 'Prepare Q3 financial forecast based on Q1/Q2 actuals and pipeline data.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Identify Cost Reduction Opportunities', 'description' => 'Analyze operational costs and identify the top 5 cost reduction opportunities across departments.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'completed'],
                ],
            ],
            [
                'agent_slug' => 'cto-agent',
                'name' => 'CTO Technology Advisor',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 80.0,
                'instructions' => 'You are the CTO-level technology advisor. Focus on technology stack evaluation, digital transformation roadmap, security posture, and cloud cost optimization.',
                'sample_tasks' => [
                    ['title' => 'Technology Stack Modernization Review', 'description' => 'Review current technology stack and identify modernization opportunities for H2 2026.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'in_progress'],
                    ['title' => 'Cybersecurity Posture Assessment', 'description' => 'Assess current cybersecurity posture and generate a risk-prioritized remediation roadmap.', 'type' => 'review', 'priority' => 'critical', 'status' => 'pending'],
                ],
            ],

            // ── FINANCE ─────────────────────────────────────────────────────

            [
                'agent_slug' => 'financial-analyst',
                'name' => 'Finance Controller Agent',
                'department' => 'finance',
                'mode' => 'semi-autonomous',
                'confidence' => 78.0,
                'instructions' => 'You are the finance controller AI for Dot Ventures. Perform financial analysis, generate budget reports, detect cost overruns, and validate expense claims. Flag anything above $10,000 for human approval.',
                'sample_tasks' => [
                    ['title' => 'June 2026 Expense Report Validation', 'description' => 'Validate all department expense reports for June 2026 against approved budgets.', 'type' => 'review', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Marketing Department Cost Overrun Alert', 'description' => 'Marketing department has exceeded Q2 budget by 18%. Analyze and recommend corrective action.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Capital Expenditure Tracking Report', 'description' => 'Generate CapEx tracking report for IT infrastructure investments in H1 2026.', 'type' => 'report', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'ROI Calculation: CRM System Upgrade', 'description' => 'Calculate ROI for the CRM system upgrade investment made in March 2026.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'budget-planner',
                'name' => 'Accounts Payable Agent',
                'department' => 'finance',
                'mode' => 'semi-autonomous',
                'confidence' => 76.0,
                'instructions' => 'You handle accounts payable operations. Validate vendor invoices, detect duplicates, match purchase orders, and schedule payments. All payments above $5,000 require human approval before processing.',
                'sample_tasks' => [
                    ['title' => 'Validate 12 Pending Vendor Invoices', 'description' => 'Validate pending vendor invoices from AWS, Salesforce, and 10 other suppliers. Flag any anomalies.', 'type' => 'review', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Duplicate Invoice Detection — June Batch', 'description' => 'Scan June invoice batch for duplicate submissions across all vendors.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Vendor Payment Schedule — Week of June 10', 'description' => 'Generate optimized vendor payment schedule for the week considering cash flow constraints.', 'type' => 'action', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Banking Details Verification: 3 New Vendors', 'description' => 'Verify and validate banking details for 3 newly onboarded vendors before first payment.', 'type' => 'review', 'priority' => 'critical', 'status' => 'in_progress'],
                ],
            ],
            [
                'agent_slug' => 'risk-analyst',
                'name' => 'Accounts Receivable Agent',
                'department' => 'finance',
                'mode' => 'advisory',
                'confidence' => 77.0,
                'instructions' => 'You manage accounts receivable. Monitor outstanding payments, predict late payments, send reminders, and flag collection risks. Generate customer aging reports weekly.',
                'sample_tasks' => [
                    ['title' => 'Debtor Aging Analysis — June 2026', 'description' => 'Generate debtor aging analysis and identify accounts overdue by 60+ days.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Late Payment Risk Predictions — Q2 Customers', 'description' => 'Predict which Q2 customers are at risk of late payment in July based on payment history.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'pending'],
                    ['title' => 'Payment Reminders: 8 Overdue Accounts', 'description' => 'Send automated payment reminders to 8 accounts with outstanding invoices over 30 days.', 'type' => 'action', 'priority' => 'medium', 'status' => 'completed'],
                ],
            ],

            // ── HR ───────────────────────────────────────────────────────────

            [
                'agent_slug' => 'recruiter-agent',
                'name' => 'Recruitment Agent',
                'department' => 'hr',
                'mode' => 'semi-autonomous',
                'confidence' => 75.0,
                'instructions' => 'You handle recruitment for Dot Ventures. Screen CVs, rank candidates, generate job descriptions, and schedule interviews. Never make final hiring decisions without HR director approval.',
                'sample_tasks' => [
                    ['title' => 'Screen 47 Applications: Senior Engineer Role', 'description' => 'Screen and rank 47 applications for the Senior Backend Engineer position. Top 5 move to phone screen.', 'type' => 'review', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Generate Job Description: Data Analyst', 'description' => 'Create a compelling job description for a Data Analyst position in the Finance team.', 'type' => 'action', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'Interview Scheduling: 5 Final Round Candidates', 'description' => 'Coordinate and schedule final round interviews for 5 shortlisted Product Manager candidates.', 'type' => 'action', 'priority' => 'high', 'status' => 'in_progress'],
                    ['title' => 'Recruitment Funnel Report — June 2026', 'description' => 'Generate recruitment funnel analysis for all open roles in June 2026.', 'type' => 'report', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'performance-agent',
                'name' => 'HR Manager Agent',
                'department' => 'hr',
                'mode' => 'advisory',
                'confidence' => 76.0,
                'instructions' => 'You support HR operations. Monitor workforce health, track leave balances, analyze turnover trends, and generate HR reports. Performance reviews require manager and HR director approval.',
                'sample_tasks' => [
                    ['title' => 'Q2 Employee Performance Review Summary', 'description' => 'Compile Q2 performance review data and identify top performers and those needing development support.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Employee Turnover Analysis — H1 2026', 'description' => 'Analyze H1 2026 turnover patterns and identify risk departments.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'pending'],
                    ['title' => 'Training Compliance Check — IT Department', 'description' => 'Verify training compliance for IT department on mandatory security awareness program.', 'type' => 'review', 'priority' => 'high', 'status' => 'completed'],
                ],
            ],
            [
                'agent_slug' => 'training-agent',
                'name' => 'Employee Training Agent',
                'department' => 'hr',
                'mode' => 'semi-autonomous',
                'confidence' => 77.0,
                'instructions' => 'You design and deliver employee training plans. Create personalized learning paths, track completion, and identify skill gaps. All new mandatory training programs need HR director sign-off.',
                'sample_tasks' => [
                    ['title' => 'Onboarding Plan: 3 New Hires — June 2026', 'description' => 'Create personalized 30-day onboarding plans for 3 new hires joining in June 2026.', 'type' => 'action', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Skill Gap Analysis: Sales Team', 'description' => 'Analyze skill gaps in the Sales team and recommend targeted training interventions.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],

            // ── IT ───────────────────────────────────────────────────────────

            [
                'agent_slug' => 'it-support-agent',
                'name' => 'IT Operations Agent',
                'department' => 'it',
                'mode' => 'semi-autonomous',
                'confidence' => 80.0,
                'instructions' => 'You manage IT operations for Dot Ventures. Triage support tickets, monitor system health, track incidents, and manage asset inventory. Service outages and critical incidents auto-escalate to the IT Head.',
                'sample_tasks' => [
                    ['title' => 'Triage: 23 Open Support Tickets', 'description' => 'Triage and prioritize 23 open IT support tickets. Resolve Tier-1 issues directly.', 'type' => 'action', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Monthly Infrastructure Utilization Report', 'description' => 'Generate monthly AWS infrastructure utilization report and identify cost optimization opportunities.', 'type' => 'report', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'Service Outage RCA: CRM Downtime June 8', 'description' => 'Produce root cause analysis for the 2-hour CRM system downtime on June 8, 2026.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'in_progress'],
                    ['title' => 'Asset Inventory Audit — Q2 2026', 'description' => 'Conduct quarterly IT asset inventory audit and update CMDB records.', 'type' => 'review', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'cybersecurity-agent',
                'name' => 'Security Operations Agent',
                'department' => 'it',
                'mode' => 'advisory',
                'confidence' => 82.0,
                'instructions' => 'You monitor and protect Dot Ventures from security threats. Analyze security events, detect anomalies, review access violations, and recommend remediations. Critical threats trigger immediate escalation to CISO.',
                'sample_tasks' => [
                    ['title' => 'Weekly Security Incident Summary', 'description' => 'Generate weekly security incident summary report with threat trends and remediation status.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Privilege Escalation Alert: User ID 1847', 'description' => 'Investigate suspected privilege escalation attempt by user account 1847 at 02:34 UTC June 9.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Access Violation Review: Finance Portal', 'description' => 'Review 3 flagged unauthorized access attempts to the Finance portal in the past 7 days.', 'type' => 'review', 'priority' => 'critical', 'status' => 'completed'],
                ],
            ],
            [
                'agent_slug' => 'cloud-architect-agent',
                'name' => 'Cloud Infrastructure Agent',
                'department' => 'it',
                'mode' => 'advisory',
                'confidence' => 79.0,
                'instructions' => 'You advise on cloud architecture for Dot Ventures (primary: AWS). Identify cost optimization opportunities, design scalable architectures, and review infrastructure changes. Major architecture changes need CTO approval.',
                'sample_tasks' => [
                    ['title' => 'AWS Cost Optimization Review — June 2026', 'description' => 'Analyze AWS billing for June and identify quick-win cost optimizations.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Architecture Review: New Payment Service', 'description' => 'Review proposed architecture for new payment microservice before development begins.', 'type' => 'review', 'priority' => 'high', 'status' => 'pending'],
                ],
            ],

            // ── SALES ────────────────────────────────────────────────────────

            [
                'agent_slug' => 'lead-generation-agent',
                'name' => 'CRM & Lead Agent',
                'department' => 'sales',
                'mode' => 'semi-autonomous',
                'confidence' => 75.0,
                'instructions' => 'You handle lead generation and CRM management for Dot Ventures. Qualify leads, score opportunities, track deal progress, and identify churn risks. Outreach emails require sales manager approval before sending.',
                'sample_tasks' => [
                    ['title' => 'Qualify 35 Inbound Leads — June Week 2', 'description' => 'Qualify and score 35 inbound leads from the June marketing campaign based on ICP criteria.', 'type' => 'review', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Churn Risk Analysis: Enterprise Tier Customers', 'description' => 'Identify enterprise customers showing churn risk signals in the past 60 days.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'pending'],
                    ['title' => 'Pipeline Health Check — June 10', 'description' => 'Generate pipeline health report including deal velocity, conversion rates, and at-risk deals.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Follow-Up Sequences: 12 Stalled Deals', 'description' => 'Generate personalized follow-up recommendations for 12 deals stalled for 21+ days.', 'type' => 'action', 'priority' => 'medium', 'status' => 'in_progress'],
                ],
            ],
            [
                'agent_slug' => 'sales-forecast-agent',
                'name' => 'Sales Manager Agent',
                'department' => 'sales',
                'mode' => 'advisory',
                'confidence' => 78.0,
                'instructions' => 'You support sales management for Dot Ventures. Monitor sales performance, forecast revenue, track targets, and recommend actions. Revenue forecasts above $100K require VP Sales review.',
                'sample_tasks' => [
                    ['title' => 'June 2026 Revenue Forecast', 'description' => 'Generate June 2026 revenue forecast with 3 scenarios (base, upside, downside) and confidence intervals.', 'type' => 'report', 'priority' => 'critical', 'status' => 'completed'],
                    ['title' => 'Sales Target Attainment Analysis — Q2', 'description' => 'Analyze Q2 sales target attainment by rep and territory. Identify coaching opportunities.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'pending'],
                    ['title' => 'Win/Loss Analysis: Q2 Enterprise Deals', 'description' => 'Analyze win/loss patterns in Q2 enterprise deals and identify key improvement areas.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'completed'],
                ],
            ],

            // ── MARKETING ───────────────────────────────────────────────────

            [
                'agent_slug' => 'content-agent',
                'name' => 'Marketing Content Agent',
                'department' => 'marketing',
                'mode' => 'semi-autonomous',
                'confidence' => 77.0,
                'instructions' => 'You create marketing content for Dot Ventures. Write blog posts, emails, social copy, and case studies. All external-facing content must be reviewed and approved by the Marketing Director before publishing.',
                'sample_tasks' => [
                    ['title' => 'Blog Post: "5 Ways AI Agents Transform Finance Teams"', 'description' => 'Write a 1,200-word SEO-optimized blog post on AI transformation in finance departments.', 'type' => 'action', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'Email Nurture Sequence: New Trial Signups', 'description' => 'Create a 5-email nurture sequence for new trial users to drive activation.', 'type' => 'action', 'priority' => 'high', 'status' => 'in_progress'],
                    ['title' => 'Q3 Product Launch Content Plan', 'description' => 'Develop a content calendar for the Q3 product launch including blog, social, and email.', 'type' => 'report', 'priority' => 'high', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'market-research-agent',
                'name' => 'Marketing Strategy Agent',
                'department' => 'marketing',
                'mode' => 'advisory',
                'confidence' => 78.0,
                'instructions' => 'You drive marketing strategy for Dot Ventures. Analyze campaign performance, monitor brand sentiment, research competitors, and recommend marketing investments. Budget decisions above $20K need CMO approval.',
                'sample_tasks' => [
                    ['title' => 'Q2 Campaign Performance Analysis', 'description' => 'Analyze Q2 marketing campaign performance across paid, organic, and email channels.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Competitor Intelligence Report: Q2 2026', 'description' => 'Compile competitive intelligence on top 3 competitors: product updates, pricing, and messaging changes.', 'type' => 'report', 'priority' => 'medium', 'status' => 'pending'],
                    ['title' => 'Brand Sentiment Monitor: June 2026', 'description' => 'Monitor and summarize brand sentiment across review sites, social media, and forums.', 'type' => 'report', 'priority' => 'low', 'status' => 'completed'],
                ],
            ],
            [
                'agent_slug' => 'seo-agent',
                'name' => 'SEO & Growth Agent',
                'department' => 'marketing',
                'mode' => 'advisory',
                'confidence' => 76.0,
                'instructions' => 'You manage SEO and organic growth for Dot Ventures. Conduct keyword research, audit content, identify ranking opportunities, and track organic traffic. Content publishing requires Marketing Director approval.',
                'sample_tasks' => [
                    ['title' => 'Monthly SEO Performance Report — June 2026', 'description' => 'Generate monthly organic search performance report including rankings, traffic, and conversion data.', 'type' => 'report', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'Keyword Gap Analysis: Competitor Comparison', 'description' => 'Identify keyword gaps vs. top 3 competitors and prioritize quick-win opportunities.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],

            // ── OPERATIONS ───────────────────────────────────────────────────

            [
                'agent_slug' => 'operations-manager-agent',
                'name' => 'Operations Manager Agent',
                'department' => 'operations',
                'mode' => 'semi-autonomous',
                'confidence' => 76.0,
                'instructions' => 'You manage operations for Dot Ventures. Monitor production KPIs, identify bottlenecks, analyze workflow efficiency, and recommend process improvements. Changes to core operational workflows need COO approval.',
                'sample_tasks' => [
                    ['title' => 'June Operational KPI Dashboard', 'description' => 'Generate June operational KPI dashboard across customer onboarding, support, and delivery processes.', 'type' => 'report', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Bottleneck Analysis: Customer Onboarding Flow', 'description' => 'Analyze the customer onboarding process to identify steps causing delays and drop-off.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'in_progress'],
                    ['title' => 'Q3 Service Level Review', 'description' => 'Review service level performance against SLA targets for Q2 and set Q3 improvement targets.', 'type' => 'review', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'supply-chain-agent',
                'name' => 'Supply Chain Agent',
                'department' => 'operations',
                'mode' => 'advisory',
                'confidence' => 75.0,
                'instructions' => 'You monitor supply chain health for Dot Ventures. Track vendor performance, identify supply risks, optimize procurement, and forecast inventory needs. Procurement decisions above $15K need COO approval.',
                'sample_tasks' => [
                    ['title' => 'Vendor Performance Review: Top 10 Suppliers', 'description' => 'Review Q2 performance of top 10 suppliers against SLA and quality metrics.', 'type' => 'review', 'priority' => 'medium', 'status' => 'completed'],
                    ['title' => 'Supply Risk Assessment: Critical Components', 'description' => 'Identify supply chain risks for critical components and recommend mitigation strategies.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'pending'],
                ],
            ],

            // ── CUSTOMER SERVICE ─────────────────────────────────────────────

            [
                'agent_slug' => 'customer-support-agent',
                'name' => 'Customer Support Agent',
                'department' => 'executive',
                'mode' => 'semi-autonomous',
                'confidence' => 80.0,
                'instructions' => 'You handle customer support for Dot Ventures. Resolve Tier-1 inquiries, create tickets, escalate complex issues, and track customer satisfaction. Refunds above $500 and account deletions require human approval.',
                'sample_tasks' => [
                    ['title' => 'Resolve: 15 Pending Customer Inquiries', 'description' => 'Process and respond to 15 pending customer support inquiries in the queue.', 'type' => 'action', 'priority' => 'high', 'status' => 'completed'],
                    ['title' => 'Customer Satisfaction Analysis — June 2026', 'description' => 'Analyze June customer satisfaction scores and identify recurring pain points.', 'type' => 'analysis', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'customer-success-agent',
                'name' => 'Customer Success Agent',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 77.0,
                'instructions' => 'You drive customer success for Dot Ventures. Monitor customer health scores, predict churn, identify expansion opportunities, and prepare QBR materials. Account expansions and renewals need CS Manager approval.',
                'sample_tasks' => [
                    ['title' => 'Churn Risk Alerts — June 10, 2026', 'description' => 'Identify and analyze customers showing churn risk signals in the last 14 days.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'completed'],
                    ['title' => 'Expansion Opportunity Map: Enterprise Accounts', 'description' => 'Identify upsell and expansion opportunities across enterprise tier customers.', 'type' => 'analysis', 'priority' => 'high', 'status' => 'pending'],
                ],
            ],

            // ── LEGAL & COMPLIANCE ───────────────────────────────────────────

            [
                'agent_slug' => 'contract-review-agent',
                'name' => 'Contract Review Agent',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 82.0,
                'instructions' => 'You review contracts for Dot Ventures. Identify risk clauses, flag non-standard terms, and suggest amendments. All contract recommendations must be reviewed by the Legal team before acceptance or rejection.',
                'sample_tasks' => [
                    ['title' => 'Review: AWS Enterprise Agreement Renewal', 'description' => 'Review AWS enterprise agreement renewal terms and flag any changes from previous agreement.', 'type' => 'review', 'priority' => 'critical', 'status' => 'completed'],
                    ['title' => 'Review: 3 New Vendor NDAs', 'description' => 'Review and summarize 3 incoming vendor NDAs. Highlight non-standard clauses.', 'type' => 'review', 'priority' => 'high', 'status' => 'in_progress'],
                    ['title' => 'Contract Risk Summary: Q2 Executed Contracts', 'description' => 'Generate risk summary of all contracts executed in Q2 2026.', 'type' => 'report', 'priority' => 'medium', 'status' => 'pending'],
                ],
            ],
            [
                'agent_slug' => 'compliance-agent',
                'name' => 'Compliance & Governance Agent',
                'department' => 'executive',
                'mode' => 'advisory',
                'confidence' => 83.0,
                'instructions' => 'You monitor regulatory compliance for Dot Ventures. Track GDPR, SOC 2, and industry regulations, identify compliance gaps, and recommend remediations. Compliance policy changes require Legal Director approval.',
                'sample_tasks' => [
                    ['title' => 'GDPR Compliance Gap Assessment — H1 2026', 'description' => 'Assess GDPR compliance gaps across all data processing activities in H1 2026.', 'type' => 'analysis', 'priority' => 'critical', 'status' => 'completed'],
                    ['title' => 'SOC 2 Readiness Check: Q3 Audit Prep', 'description' => 'Review SOC 2 control status and generate readiness report for upcoming Q3 audit.', 'type' => 'review', 'priority' => 'critical', 'status' => 'pending'],
                ],
            ],
        ];
    }
}
