<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_researches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplement_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('error', 500)->nullable();
            $table->text('summary')->nullable();
            $table->text('safety_notes')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_researches');
    }
};
