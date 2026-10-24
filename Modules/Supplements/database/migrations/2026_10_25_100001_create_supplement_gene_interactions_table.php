<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_gene_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplement_id')->constrained()->cascadeOnDelete();
            $table->string('gene', 20);
            $table->string('rsid', 20)->nullable();
            $table->char('allele', 1)->nullable();
            $table->string('relation', 10);
            $table->text('explanation');
            $table->json('source_keys');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_gene_interactions');
    }
};
