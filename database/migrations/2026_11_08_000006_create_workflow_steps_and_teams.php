<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->longText('graph')->nullable();
            $table->boolean('is_test')->default(false);
            $table->string('idempotency_key', 120)->nullable();
            $table->timestamp('advancing_until')->nullable();
            $table->unique(['organization_id', 'idempotency_key']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
            $table->string('node_uuid', 40);
            $table->string('label');
            $table->string('kind', 20);
            $table->string('status', 20)->default('pending');
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_execution_id', 'node_uuid']);
            $table->index(['organization_id', 'status']);
        });

        Schema::table('agent_approvals', function (Blueprint $table) {
            $table->foreignId('workflow_step_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('agent_effects', function (Blueprint $table) {
            $table->unsignedBigInteger('child_run_id')->nullable()->index();
        });

        Schema::create('agent_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('purpose')->nullable();
            $table->foreignId('supervisor_deployment_id')->constrained('agent_deployments')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'supervisor_deployment_id']);
        });

        Schema::create('agent_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->string('responsibility', 500)->nullable();
            $table->timestamps();

            $table->unique(['agent_team_id', 'agent_deployment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_team_members');
        Schema::dropIfExists('agent_teams');

        Schema::table('agent_effects', function (Blueprint $table) {
            $table->dropIndex(['child_run_id']);
            $table->dropColumn('child_run_id');
        });

        Schema::table('agent_approvals', fn (Blueprint $table) => $table->dropConstrainedForeignId('workflow_step_id'));
        Schema::dropIfExists('workflow_steps');

        Schema::table('workflow_executions', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'idempotency_key']);
            $table->dropColumn(['graph', 'is_test', 'idempotency_key', 'advancing_until']);
        });
    }
};
