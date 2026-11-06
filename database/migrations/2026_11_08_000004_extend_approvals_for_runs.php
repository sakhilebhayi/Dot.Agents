<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable()->change();
            $table->foreignId('agent_run_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('agent_effect_id')->nullable()->constrained()->nullOnDelete();
            $table->char('action_hash', 64)->nullable();
            $table->string('gate_code', 40)->nullable();
            $table->index(['organization_id', 'status', 'expires_at']);
        });

        Schema::create('agent_approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['agent_approval_id', 'id']);
        });

        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('tool_key', 120)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('approver_role', 30)->nullable();
            $table->unsignedInteger('expires_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
        Schema::dropIfExists('agent_approval_events');

        Schema::table('agent_approvals', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'expires_at']);
            $table->dropConstrainedForeignId('agent_effect_id');
            $table->dropConstrainedForeignId('agent_run_id');
            $table->dropColumn(['action_hash', 'gate_code']);
        });
    }
};
