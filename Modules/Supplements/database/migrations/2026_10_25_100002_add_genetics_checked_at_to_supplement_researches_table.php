<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplement_researches', function (Blueprint $table) {
            $table->timestamp('genetics_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplement_researches', function (Blueprint $table) {
            $table->dropColumn('genetics_checked_at');
        });
    }
};
