<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('supplement_researches')->cascadeOnDelete();
            $table->string('claim', 500);
            $table->string('outcome', 50);
            $table->string('evidence_grade', 20);
            $table->json('source_keys');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_effects');
    }
};
