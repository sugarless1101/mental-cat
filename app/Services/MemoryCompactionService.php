<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\LlmLog;
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
        $this->compactMessages($user, $user->id);
        $this->compactSummaries($user, $user->id);
    }

    /**
     * chat_messages が 50件超 → 古い 30件を要約して memory_summary に保存・削除
     */
    private function compactMessages(User $user, ?int $userId = null): void
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

        $summary = $this->summarize($text, $userId);
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
    private function compactSummaries(User $user, ?int $userId = null): void
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

        $merged = $this->summarize($text, $userId);
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

    private function summarize(string $text, ?int $userId = null): ?string
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return null;
        }

        $startedAt = microtime(true);

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

            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
            $tokensIn = $response->json('usage.prompt_tokens') ?? 0;
            $tokensOut = $response->json('usage.completion_tokens') ?? 0;

            // コンパクション呼び出しも llm_logs に記録（コスト可観測性）
            try {
                LlmLog::create([
                    'user_id' => $userId,
                    'model' => 'gpt-4o',
                    'prompt_version' => 'compaction-v1',
                    'tokens_in' => $tokensIn,
                    'tokens_out' => $tokensOut,
                    'cost_estimate' => ($tokensIn * 0.0000025) + ($tokensOut * 0.00001),
                    'latency_ms' => $latencyMs,
                    'ok' => true,
                ]);
            } catch (\Exception $e) {
                Log::warning('MemoryCompactionService: failed to write LlmLog', ['message' => $e->getMessage()]);
            }

            return $response->json('choices.0.message.content') ?? null;
        } catch (\Exception $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
            Log::error('MemoryCompactionService: OpenAI call failed', ['message' => $e->getMessage()]);

            try {
                LlmLog::create([
                    'user_id' => $userId,
                    'model' => 'gpt-4o',
                    'prompt_version' => 'compaction-v1',
                    'tokens_in' => 0,
                    'tokens_out' => 0,
                    'cost_estimate' => 0,
                    'latency_ms' => $latencyMs,
                    'ok' => false,
                    'error_message' => 'compaction failed: '.$e->getMessage(),
                ]);
            } catch (\Exception $logEx) {
                Log::warning('MemoryCompactionService: failed to write error LlmLog', ['message' => $logEx->getMessage()]);
            }

            return null;
        }
    }
}
