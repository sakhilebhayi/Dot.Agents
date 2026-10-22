<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an organization here to the company (team) in InfoDot, the ecosystem's
 * identity authority, with the same column name and shape as teams.infodot_team_uuid.
 * Every data policy in this app checks organization membership, so an InfoDot
 * company only has data (and a role) here once it has an organization of its own.
 * Additive and nullable: existing organizations stay unlinked and untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations') && ! Schema::hasColumn('organizations', 'infodot_team_uuid')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('infodot_team_uuid', 36)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'infodot_team_uuid')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropUnique(['infodot_team_uuid']);
                $table->dropColumn('infodot_team_uuid');
            });
        }
    }
};
