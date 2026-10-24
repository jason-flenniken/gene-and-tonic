<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One processing run per uploaded file. `path` points at the raw file on the
     * genetics disk and is cleared once the file is deleted.
     */
    public function up(): void
    {
        Schema::create('genetic_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 255)->nullable();
            $table->string('status', 20);
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('stored_count')->default(0);
            $table->unsignedInteger('queued_lookups')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genetic_uploads');
    }
};
