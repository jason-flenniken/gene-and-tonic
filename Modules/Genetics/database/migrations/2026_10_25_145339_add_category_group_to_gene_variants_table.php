<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The broad group a reference variant is filed under (a VariantGroup value). Null until set.
     */
    public function up(): void
    {
        Schema::table('gene_variants', function (Blueprint $table) {
            $table->string('category_group', 40)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('gene_variants', function (Blueprint $table) {
            $table->dropColumn('category_group');
        });
    }
};
