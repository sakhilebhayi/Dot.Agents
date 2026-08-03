<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('url');
            $table->string('secret', 64);            // HMAC signing secret
            $table->json('events');                  // ['agent.deployed', 'task.completed', ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('retry_limit')->default(3);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
