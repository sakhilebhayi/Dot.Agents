<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An API a business connects. Each change is a new row, so a run can always say which version it used.
        Schema::create('custom_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('published');
            $table->string('label', 120);
            $table->text('description');
            $table->string('method', 10)->default('GET');
            $table->string('url', 2000);
            $table->text('headers')->nullable();
            $table->json('input_schema');
            $table->boolean('reads_sensitive')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_tools');
    }
};
