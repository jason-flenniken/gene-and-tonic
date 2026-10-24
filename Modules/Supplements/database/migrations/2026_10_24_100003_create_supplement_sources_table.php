<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('supplement_researches')->cascadeOnDelete();
            $table->string('source_key', 10);
            $table->string('url', 2048);
            $table->string('title', 500);
            $table->string('domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_sources');
    }
};
