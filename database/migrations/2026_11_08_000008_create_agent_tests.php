<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_test_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('input');
            $table->string('trigger_type', 20)->default('manual');
            $table->json('checks');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('agent_deployment_id');
        });

        Schema::create('agent_test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_test_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('config_version_id')->constrained('agent_config_versions')->cascadeOnDelete();
            $table->foreignId('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('running');
            $table->json('checks')->nullable();
            $table->json('measures')->nullable();
            $table->timestamps();

            $table->index(['agent_test_case_id', 'config_version_id']);
            $table->index('agent_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_test_results');
        Schema::dropIfExists('agent_test_cases');
    }
};
