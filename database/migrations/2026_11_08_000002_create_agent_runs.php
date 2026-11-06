<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record of work. A run is one agent doing one thing on one pinned version. Its steps are an append-only journal, so
 * the timeline, retries, recovery and every figure on a dashboard read the same rows. An effect is one change an agent
 * makes outside itself, written down before it happens. Usage events are the money ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_version_id')->constrained('agent_config_versions');
            $table->string('trigger_type', 20);
            $table->string('trigger_ref', 120)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('agent_session_id')->nullable()->index();
            $table->unsignedBigInteger('agent_task_id')->nullable()->index();
            $table->unsignedBigInteger('workflow_execution_id')->nullable()->index();
            $table->unsignedBigInteger('parent_run_id')->nullable()->index();
            $table->string('status', 24)->default('queued');
            $table->string('summary', 200)->nullable();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('taint')->default(0);
            $table->json('limits');
            $table->unsignedInteger('steps_used')->default(0);
            $table->unsignedInteger('tool_calls_used')->default(0);
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->decimal('wasted_cost', 12, 6)->default(0);
            $table->unsignedInteger('attempt')->default(0);
            $table->timestamp('leased_until')->nullable();
            $table->string('lease_owner', 40)->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->string('idempotency_key', 120)->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['agent_deployment_id', 'status']);
            $table->index(['status', 'leased_until']);
        });

        Schema::create('agent_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('kind', 20);
            $table->string('status', 20)->default('done');
            $table->string('title', 200);
            $table->longText('payload')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('tool_key', 120)->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamps();

            $table->unique(['agent_run_id', 'sequence']);
        });

        Schema::create('agent_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('call_id', 120);
            $table->string('tool_key', 120);
            $table->string('idempotency_key', 80)->unique();
            $table->string('status', 20)->default('planned');
            $table->longText('arguments');
            $table->longText('result')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_run_id', 'status']);
        });

        Schema::create('model_prices', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->decimal('input_per_million', 12, 4);
            $table->decimal('output_per_million', 12, 4);
            $table->decimal('cache_read_per_million', 12, 4)->nullable();
            $table->date('effective_from');
            $table->string('source', 200);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'model', 'effective_from']);
        });

        Schema::create('agent_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('agent_run_step_id')->nullable();
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost', 12, 6)->nullable();
            $table->unsignedBigInteger('model_price_id')->nullable();
            $table->boolean('wasted')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['agent_deployment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_usage_events');
        Schema::dropIfExists('model_prices');
        Schema::dropIfExists('agent_effects');
        Schema::dropIfExists('agent_run_steps');
        Schema::dropIfExists('agent_runs');
    }
};
