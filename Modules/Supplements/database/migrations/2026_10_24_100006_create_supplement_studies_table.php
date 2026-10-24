<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('supplement_researches')->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('design', 100);
            $table->string('population')->nullable();
            $table->unsignedInteger('sample_size')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->text('finding');
            $table->string('evidence_grade', 20);
            $table->string('source_key', 10);
            $table->string('pmid', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_studies');
    }
};
