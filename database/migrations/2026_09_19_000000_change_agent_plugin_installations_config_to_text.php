<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AgentPluginInstallation::$casts declares 'config' => 'encrypted:array',
 * which encrypts the array into an opaque ciphertext string before writing —
 * but the column itself was a native json column, which Postgres validates
 * as real JSON syntax at the database level. The encrypted string is not
 * valid JSON, so every real write failed outright on Postgres (silently
 * accepted by sqlite's lenient, unvalidated json "type"). A plain text
 * column is correct here since Eloquent's cast — not the database — owns
 * the serialization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_plugin_installations', function (Blueprint $table) {
            $table->text('config')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agent_plugin_installations', function (Blueprint $table) {
            $table->json('config')->nullable()->change();
        });
    }
};
