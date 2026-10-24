<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usage log only: no prompt, instructions or response body columns, by design.
     */
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 16);
            $table->string('profile')->nullable();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('effort', 32)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('credits')->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->string('status', 16);
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
