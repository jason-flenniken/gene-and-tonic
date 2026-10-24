<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplement_effects', function (Blueprint $table) {
            $table->string('outcome', 1000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('supplement_effects', function (Blueprint $table) {
            $table->string('outcome', 50)->change();
        });
    }
};
