<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_purge_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('model_class');
            $table->unsignedInteger('eligible_count');
            $table->text('retention_summary');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->unsignedInteger('deleted_count')->nullable();
            $table->timestamps();
            $table->index(['status', 'model_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_purge_proposals');
    }
};
