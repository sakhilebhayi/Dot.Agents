<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A key belongs to one business. Existing keys carry it in their name, `org-12:...`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('tokenable_id')->index();
        });

        DB::table('personal_access_tokens')->where('name', 'like', 'org-%')->orderBy('id')->each(function (object $token) {
            if (preg_match('/^org-(\d+):/', (string) $token->name, $match)) {
                DB::table('personal_access_tokens')->where('id', $token->id)->update(['organization_id' => (int) $match[1]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
