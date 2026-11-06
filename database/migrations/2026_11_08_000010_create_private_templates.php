<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A business's own starting points. Each save of the same name is a new version.
        Schema::create('business_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->string('role', 120)->nullable();
            $table->string('responsibility', 500)->nullable();
            $table->string('summary', 500)->nullable();
            $table->longText('config');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'name', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_templates');
    }
};
