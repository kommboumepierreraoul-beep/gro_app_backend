<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->enum('mode', ['amiable', 'admin'])->default('amiable')->after('status');
            $table->timestamp('escalated_at')->nullable()->after('mode');
            $table->text('admin_question')->nullable()->after('escalated_at');
        });
    }

    public function down()
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['mode', 'escalated_at', 'admin_question']);
        });
    }
};