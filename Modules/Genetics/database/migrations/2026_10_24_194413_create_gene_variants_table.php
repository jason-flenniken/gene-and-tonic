<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global SNP reference data: one row per rsID, no user column.
     */
    public function up(): void
    {
        Schema::create('gene_variants', function (Blueprint $table) {
            $table->id();
            $table->string('rsid', 20)->unique();
            $table->string('gene', 20)->nullable();
            $table->char('reference_allele', 1)->nullable();
            $table->char('risk_allele', 1)->nullable();
            $table->string('category', 60)->nullable();
            $table->text('affects')->nullable();
            $table->json('genotype_effects')->nullable();
            $table->json('sources')->nullable();
            $table->string('status', 20);
            $table->string('error', 255)->nullable();
            $table->timestamp('looked_up_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gene_variants');
    }
};
