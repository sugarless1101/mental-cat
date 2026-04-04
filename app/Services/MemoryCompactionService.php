<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoryCompactionService
{
    private const MESSAGE_THRESHOLD = 50;

    private const COMPACT_COUNT = 30;

    private const SUMMARY_THRESHOLD = 10;

    private const SUMMARY_COMPACT = 5;

    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';

    private const TIMEOUT = 30;

    public function compact(User $user): void
    {
        $this->compactMessages($user);
        $this->compactSummaries($user);
    }

    /**
     * chat_messages が 50件超 → 古い 30件を要約して memory_summary に保存・削除
     */
    private function compactMessages(User $user): void
    {
        $total = $user->chatMessages()->count();
        if ($total <= self::MESSAGE_THRESHOLD) {
            return;
        }

        $oldest = $user->chatMessages()
            ->orderBy('created_at')
            ->limit(self::COMPACT_COUNT)
            ->get();

        if ($oldest->isEmpty()) {
            return;
        }

        $text = $oldest->map(fn ($m) => ($m->role === 'user' ? 'ユーザー' : '猫').': '.$m->content)
            ->implode("\n");

        $summary = $this->summarize($text);
        if (! $summary) {
            Log::warning('MemoryCompactionService: summarize failed for user', ['user_id' => $user->id]);

            return;
        }

        // 要約を新しいアシスタントメッセージとして保存
        ChatMessage::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => '[記憶の圧縮]',
            'memory_summary' => $summary,
        ]);

        // 圧縮した元メッセージを削除
        $user->chatMessages()
            ->whereIn('id', $oldest->pluck('id'))
            ->delete();

        Log::info('MemoryCompactionService: compacted messages', [
            'user_id' => $user->id,
            'deleted' => $oldest->count(),
        ]);
    }

    /**
     * memory_summary が 10件超 → 古い 5件を再要約して 1件に置換
     */
    private function compactSummaries(User $user): void
    {
        $summaries = $user->chatMessages()
            ->whereNotNull('memory_summary')
            ->orderBy('created_at')
            ->get(['id', 'memory_summary']);

        if ($summaries->count() <= self::SUMMARY_THRESHOLD) {
            return;
        }

        $oldest = $summaries->take(self::SUMMARY_COMPACT);
        $text = $oldest->pluck('memory_summary')->implode("\n");

        $merged = $this->summarize($text);
        if (! $merged) {
            Log::warning('MemoryCompactionService: summary merge failed for user', ['user_id' => $user->id]);

            return;
        }

        // 古い要約を削除
        $user->chatMessages()
            ->whereIn('id', $oldest->pluck('id'))
            ->delete();

        // 統合要約を保存
        ChatMessage::create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => '[記憶の統合]',
            'memory_summary' => $merged,
        ]);

        Log::info('MemoryCompactionService: merged summaries', [
            'user_id' => $user->id,
            'merged' => $oldest->count(),
        ]);
    }

    private function summarize(string $text): ?string
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->timeout(self::TIMEOUT)
                ->post(self::OPENAI_API_URL, [
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => '以下の会話履歴を100文字以内の日本語で要点をまとめてください。箇条書き不要。1文で。',
                        ],
                        [
                            'role' => 'user',
                            'content' => $text,
                        ],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 150,
                ])
                ->throw();

            return $response->json('choices.0.message.content') ?? null;
        } catch (\Exception $e) {
            Log::error('MemoryCompactionService: OpenAI call failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
