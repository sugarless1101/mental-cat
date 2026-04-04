<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('llm_logs', function (Blueprint $table) {
            // null=未評価, true=👍, false=👎
            $table->boolean('feedback')->nullable()->after('error_message');
            $table->unsignedBigInteger('chat_message_id')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('llm_logs', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'chat_message_id']);
        });
    }
};
