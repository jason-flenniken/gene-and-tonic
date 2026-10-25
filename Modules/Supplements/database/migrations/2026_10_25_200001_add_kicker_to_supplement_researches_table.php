<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplement_researches', function (Blueprint $table) {
            $table->string('kicker', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplement_researches', function (Blueprint $table) {
            $table->dropColumn('kicker');
        });
    }
};
