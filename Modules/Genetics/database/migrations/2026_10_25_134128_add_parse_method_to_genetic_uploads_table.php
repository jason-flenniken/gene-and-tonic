<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How an upload was read: locally, locally with the unread rows sent to the
     * model, or by the model alone, and how many rows each way. Null on rows
     * processed before this was recorded.
     */
    public function up(): void
    {
        Schema::table('genetic_uploads', function (Blueprint $table) {
            $table->string('parse_method', 20)->nullable()->after('error');
            $table->unsignedInteger('local_count')->default(0)->after('parse_method');
            $table->unsignedInteger('ai_count')->default(0)->after('local_count');
            $table->unsignedInteger('skipped_count')->default(0)->after('ai_count');
        });
    }

    public function down(): void
    {
        Schema::table('genetic_uploads', function (Blueprint $table) {
            $table->dropColumn(['parse_method', 'local_count', 'ai_count', 'skipped_count']);
        });
    }
};
