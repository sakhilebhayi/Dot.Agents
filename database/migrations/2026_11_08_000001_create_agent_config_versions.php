<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agents belong to a business, and what an agent is set up to do is a numbered, immutable version.
 *
 * `agents` stays the catalogue: no owner means an official template, an owner means that business's own. A deployment
 * stays the working agent. Every existing deployment gets version 1 from what it holds today, so nothing live changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('template')->nullable();
        });

        Schema::table('agent_deployments', function (Blueprint $table) {
            $table->string('role', 120)->nullable();
            $table->text('responsibility')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedTinyInteger('autonomy_level')->default(2);
        });

        Schema::create('agent_config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_deployment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('state', 20)->default('draft');
            $table->longText('config');
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_deployment_id', 'number']);
            $table->index(['agent_deployment_id', 'state']);
        });

        DB::table('agent_deployments')->orderBy('id')->each(function (object $deployment) {
            $instructions = $deployment->custom_instructions === null ? '' : Crypt::decryptString($deployment->custom_instructions);

            $id = DB::table('agent_config_versions')->insertGetId([
                'organization_id' => $deployment->organization_id,
                'agent_deployment_id' => $deployment->id,
                'number' => 1,
                'state' => 'published',
                'config' => Crypt::encryptString((string) json_encode([
                    'instructions' => $instructions,
                    'model' => ['model' => $deployment->model_override],
                ])),
                'note' => 'Carried over from the deployment',
                'published_at' => $deployment->deployed_at ?? $deployment->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('agent_deployments')->where('id', $deployment->id)->update([
                'published_version_id' => $id,
                'owner_id' => $deployment->deployed_by,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_config_versions');

        Schema::table('agent_deployments', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropColumn(['role', 'responsibility', 'owner_id', 'published_version_id', 'autonomy_level']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn(['organization_id', 'created_by', 'template']);
        });
    }
};
