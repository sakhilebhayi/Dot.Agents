<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The seven roles replace `member` and `manager`. Nothing else about a membership changes. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_user')->where('role', 'member')->update(['role' => 'operator']);
        DB::table('organization_user')->where('role', 'manager')->update(['role' => 'agent_manager']);
    }

    public function down(): void
    {
        DB::table('organization_user')->where('role', 'operator')->update(['role' => 'member']);
        DB::table('organization_user')->where('role', 'agent_manager')->update(['role' => 'manager']);
    }
};
