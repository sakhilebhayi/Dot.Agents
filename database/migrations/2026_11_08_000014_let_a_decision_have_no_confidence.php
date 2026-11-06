<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A decision a person made has no confidence figure, and a made-up one must not stand in for it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_logs', function (Blueprint $table) {
            $table->decimal('confidence_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void {}
};
