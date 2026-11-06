<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('monthly_limit', 12, 2);
            $table->unsignedTinyInteger('warn_at_percent')->default(80);
            $table->boolean('stop_at_limit')->default(true);
            $table->string('warned_for', 7)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('agent_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'agent_deployment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_favourites');
        Schema::dropIfExists('usage_budgets');
    }
};
