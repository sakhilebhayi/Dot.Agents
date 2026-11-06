<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->string('index_status', 20)->default('pending');
            $table->string('index_error')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->string('file_name')->nullable();
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_base_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('content');
            $table->text('search_text');
            $table->timestamps();

            $table->index(['organization_id', 'knowledge_base_id']);
            $table->index('knowledge_article_id');
        });

        Schema::table('agent_memories', function (Blueprint $table) {
            $table->string('scope', 20)->default('agent');
            $table->string('status', 20)->default('active');
            $table->foreignId('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 500)->nullable();
            $table->index(['organization_id', 'status', 'scope']);
        });

        // Every saved memory was a transcript of one person's conversation, so it stays with that person's agent
        // only as something to review. Nothing was recorded about whose it was.
        DB::table('agent_memories')->update(['status' => 'proposed', 'source' => 'Saved before memory had owners']);

        // Held a plaintext copy of memories kept encrypted elsewhere, and nothing reads it any more.
        Schema::dropIfExists('memory_embeddings');
    }

    public function down(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'scope']);
            $table->dropConstrainedForeignId('agent_run_id');
            $table->dropColumn(['scope', 'status', 'source']);
        });

        Schema::dropIfExists('knowledge_chunks');

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropColumn(['index_status', 'index_error', 'indexed_at', 'file_name']);
        });
    }
};
