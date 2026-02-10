<?php

namespace App\Services;

use App\Models\User;
use App\Models\ChatMessage;
use App\Models\Task;

class ChatContextBuilder
{
    /**
     * Build context text for AI prompt (following SPEC.md low-cost memory strategy)
     *
     * @param User $user
     * @return string
     */
    public function build(User $user): string
    {
        $lines = [];

        // 1. Long-term summary (latest 1 memory_summary)
        $lines[] = '=== 長期メモリ ===';
        $longTermSummary = $user->chatMessages()
            ->whereNotNull('memory_summary')
            ->latest('created_at')
            ->value('memory_summary');

        if ($longTermSummary) {
            $lines[] = "以前の要点: {$longTermSummary}";
        } else {
            $lines[] = '（まだ要点がありません）';
        }

        // 2. Short-term context (latest 10 messages)
        $lines[] = '';
        $lines[] = '=== 最近の会話（最新10件） ===';
        $recentMessages = $user->chatMessages()
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->reverse();

        if ($recentMessages->isNotEmpty()) {
            foreach ($recentMessages as $msg) {
                $role = $msg->role === 'user' ? 'ユーザー' : '猫';
                $lines[] = "{$role}: {$msg->content}";
            }
        } else {
            $lines[] = '（初めての会話です）';
        }

        // 3. Task context (latest 5 uncompleted tasks)
        $lines[] = '';
        $lines[] = '=== やることリスト（未完了上位5件） ===';
        $tasks = $user->tasks()
            ->where('status', 'todo')
            ->latest('created_at')
            ->limit(5)
            ->get();

        if ($tasks->isNotEmpty()) {
            foreach ($tasks as $task) {
                $lines[] = "- {$task->title}";
            }
        } else {
            $lines[] = '（やることがありません！）';
        }

        return implode("\n", $lines);
    }
}
