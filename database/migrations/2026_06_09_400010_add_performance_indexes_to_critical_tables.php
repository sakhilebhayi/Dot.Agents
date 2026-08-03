<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes identified in the Full-Stack Integrity Audit.
     *
     * Covers high-traffic query patterns on:
     *  - agent_tasks        (time-range queries on dashboards / governance views)
     *  - security_events    (security dashboard: by org + created_at, by type+status)
     *  - decision_logs      (delusion monitoring: by deployment + date range)
     *  - audit_logs         (compliance queries: by org + event_category + date)
     */
    public function up(): void
    {
        // NOTE: previously queried `sqlite_master` directly, which only exists on
        // SQLite connections and blew up with "Undefined table: sqlite_master" on
        // every other driver (e.g. pgsql). Use Laravel's driver-agnostic schema
        // introspection (Schema::getIndexes()) instead so this migration works on
        // any supported database.
        $agentTasksIndexes = array_column(Schema::getIndexes('agent_tasks'), 'name');
        Schema::table('agent_tasks', function (Blueprint $table) use ($agentTasksIndexes) {
            if (! in_array('agent_tasks_org_created_at_idx', $agentTasksIndexes)) {
                $table->index(['organization_id', 'created_at'], 'agent_tasks_org_created_at_idx');
            }
        });

        $securityEventsIndexes = array_column(Schema::getIndexes('security_events'), 'name');
        Schema::table('security_events', function (Blueprint $table) use ($securityEventsIndexes) {
            if (! in_array('security_events_org_created_at_idx', $securityEventsIndexes)) {
                $table->index(['organization_id', 'created_at'], 'security_events_org_created_at_idx');
            }
            if (! in_array('security_events_org_type_status_idx', $securityEventsIndexes)) {
                $table->index(['organization_id', 'event_type', 'status'], 'security_events_org_type_status_idx');
            }
        });

        $decisionLogsIndexes = array_column(Schema::getIndexes('decision_logs'), 'name');
        Schema::table('decision_logs', function (Blueprint $table) use ($decisionLogsIndexes) {
            if (! in_array('decision_logs_org_created_at_idx', $decisionLogsIndexes)) {
                $table->index(['organization_id', 'created_at'], 'decision_logs_org_created_at_idx');
            }
            if (! in_array('decision_logs_deployment_created_at_idx', $decisionLogsIndexes)) {
                $table->index(['agent_deployment_id', 'created_at'], 'decision_logs_deployment_created_at_idx');
            }
        });

        $auditLogsIndexes = array_column(Schema::getIndexes('audit_logs'), 'name');
        Schema::table('audit_logs', function (Blueprint $table) use ($auditLogsIndexes) {
            if (! in_array('audit_logs_org_created_at_idx', $auditLogsIndexes)) {
                $table->index(['organization_id', 'created_at'], 'audit_logs_org_created_at_idx');
            }
            if (! in_array('audit_logs_org_event_idx', $auditLogsIndexes)) {
                $table->index(['organization_id', 'event'], 'audit_logs_org_event_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_tasks', function (Blueprint $table) {
            $table->dropIndexIfExists('agent_tasks_org_created_at_idx');
        });

        Schema::table('security_events', function (Blueprint $table) {
            $table->dropIndexIfExists('security_events_org_created_at_idx');
            $table->dropIndexIfExists('security_events_org_type_status_idx');
        });

        Schema::table('decision_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('decision_logs_org_created_at_idx');
            $table->dropIndexIfExists('decision_logs_deployment_created_at_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('audit_logs_org_created_at_idx');
            $table->dropIndexIfExists('audit_logs_org_event_idx');
        });
    }
};
