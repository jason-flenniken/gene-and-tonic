<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user's genotype per rsID. The genotype is ciphertext (encrypted cast).
     */
    public function up(): void
    {
        Schema::create('user_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('rsid', 20);
            $table->text('genotype');
            $table->string('source', 255);
            $table->unsignedBigInteger('genetic_upload_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'rsid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_variants');
    }
};
