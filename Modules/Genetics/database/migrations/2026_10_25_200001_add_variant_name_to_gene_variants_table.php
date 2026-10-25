<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A common name for the variant, such as "A1298C" or "Val158Met". Null until looked up.
     */
    public function up(): void
    {
        Schema::table('gene_variants', function (Blueprint $table) {
            $table->string('variant_name', 40)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('gene_variants', function (Blueprint $table) {
            $table->dropColumn('variant_name');
        });
    }
};
