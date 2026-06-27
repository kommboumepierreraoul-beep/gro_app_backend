<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('media_type')->nullable()->after('media_url');
            $table->bigInteger('media_size')->nullable()->after('media_type');
            $table->string('file_name')->nullable()->after('media_size');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_size', 'file_name']);
        });
    }
};
