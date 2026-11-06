<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Webhook headers can hold credentials. Plain JSON already stored is encrypted in place. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('agent_skills')->whereNotNull('webhook_headers')->orderBy('id')->each(function (object $skill) {
            if (json_decode((string) $skill->webhook_headers) !== null) {
                DB::table('agent_skills')->where('id', $skill->id)->update([
                    'webhook_headers' => Crypt::encryptString((string) $skill->webhook_headers),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('agent_skills')->whereNotNull('webhook_headers')->orderBy('id')->each(function (object $skill) {
            DB::table('agent_skills')->where('id', $skill->id)->update([
                'webhook_headers' => Crypt::decryptString((string) $skill->webhook_headers),
            ]);
        });
    }
};
