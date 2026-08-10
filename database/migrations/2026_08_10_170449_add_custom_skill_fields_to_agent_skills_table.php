<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_skills', function (Blueprint $table) {
            // Null = platform-wide shared catalog entry (the pre-existing,
            // still-default meaning of this table) -- set = an org's own
            // custom skill, visible only to that org. Same nullable-owner
            // pattern already used by AgentPlugin/PluginOrganizationScope
            // for platform-wide-vs-org-specific rows in one table.
            $table->foreignId('organization_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();

            // A custom skill has no PHP `class` -- it's executed by calling
            // out to the organization's own endpoint instead. See
            // App\Skills\WebhookSkill and SkillRegistryService::resolve().
            $table->string('webhook_url')->nullable()->after('class');
            $table->json('webhook_headers')->nullable()->after('webhook_url');
            $table->unsignedInteger('webhook_timeout_seconds')->default(15)->after('webhook_headers');

            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_skills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['webhook_url', 'webhook_headers', 'webhook_timeout_seconds']);
        });
    }
};
