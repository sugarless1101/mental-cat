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
        Schema::table('chat_messages', function (Blueprint $table) {
            // 'chat' = ユーザー/猫の通常会話
            // 'recommendation' = AIが生成したタスク推薦文（気分選択時）
            // 'memory' = メモリ圧縮サマリー
            $table->string('type')->default('chat')->after('role');
        });

        // 既存レコードを内容で分類（後付けバックフィル）
        DB::statement("UPDATE chat_messages SET type = 'system' WHERE content = '__start__'");
        DB::statement("UPDATE chat_messages SET type = 'recommendation' WHERE content LIKE '気分に合ったおすすめタスクにゃ%'");
        DB::statement("UPDATE chat_messages SET type = 'memory' WHERE content LIKE '[記憶%'");
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
