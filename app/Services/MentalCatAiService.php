<?php

namespace App\Services;

use App\Models\LlmLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentalCatAiService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT = 30;
    private const PROMPT_VERSION = 'v1';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');

        if (!$this->apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }
    }

    /**
     * Make a response from OpenAI based on user message and context.
     *
     * @param string   $userMessage        User's message
     * @param string   $contextText        Context (long-term summary, recent messages, tasks)
     * @param bool     $allowTaskCompletion Whether to allow tasks_to_complete
     * @param int|null $userId             Authenticated user ID for logging (null = guest)
     * @return array { ok: bool, reply?: string, json?: array, error?: string }
     */
    public function makeResponse(
        string $userMessage,
        string $contextText,
        bool $allowTaskCompletion = false,
        ?int $userId = null
    ): array {
        $systemPrompt = $this->buildSystemPrompt($allowTaskCompletion);

        $messages = [
            [
                'role'    => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role'    => 'user',
                'content' => "{$contextText}\n\nユーザー発言: {$userMessage}",
            ],
        ];

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
                ->timeout(self::TIMEOUT)
                ->post(self::OPENAI_API_URL, [
                    'model'       => 'gpt-4o',
                    'messages'    => $messages,
                    'temperature' => 0.7,
                    'max_tokens'  => 800,
                ])
                ->throw();

            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
            $tokensIn  = $response->json('usage.prompt_tokens') ?? 0;
            $tokensOut = $response->json('usage.completion_tokens') ?? 0;

            $responseText = $response->json('choices.0.message.content');

            if (!$responseText) {
                $this->writeLog($userId, $latencyMs, $tokensIn, $tokensOut, false, 'Empty response from OpenAI');
                return ['ok' => false, 'error' => 'Empty response from OpenAI'];
            }

            // Markdown コードブロック形式を処理
            $cleanedText = $responseText;
            if (preg_match('/```(?:json)?\s*\n(.*)\n```/s', $responseText, $matches)) {
                $cleanedText = $matches[1];
            }

            // JSON パース
            $json = json_decode($cleanedText, true);
            if (!is_array($json)) {
                Log::warning('Failed to parse OpenAI response as JSON', [
                    'original_response' => $responseText,
                    'cleaned_response'  => $cleanedText,
                ]);
                $this->writeLog($userId, $latencyMs, $tokensIn, $tokensOut, false, 'Invalid JSON response from AI');
                return ['ok' => false, 'error' => 'Invalid JSON response from AI'];
            }

            // Guardrails
            $json = $this->validateAndSanitize($json);

            $this->writeLog($userId, $latencyMs, $tokensIn, $tokensOut, true);

            return [
                'ok'    => true,
                'reply' => $json['reply'] ?? null,
                'json'  => $json,
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
            Log::error('OpenAI API request failed', [
                'status'  => $e->response?->status(),
                'message' => $e->getMessage(),
            ]);
            $this->writeLog($userId, $latencyMs, 0, 0, false, 'OpenAI API error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'OpenAI API error: ' . $e->getMessage()];
        } catch (\Exception $e) {
            $latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
            Log::error('Unexpected error in MentalCatAiService', ['message' => $e->getMessage()]);
            $this->writeLog($userId, $latencyMs, 0, 0, false, 'Unexpected error: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Unexpected error: ' . $e->getMessage()];
        }
    }

    /**
     * Validate and sanitize AI JSON output (Guardrails).
     */
    public static function validateAndSanitize(array $json): array
    {
        // reply が空 → フォールバック
        if (empty($json['reply'])) {
            $json['reply'] = self::getFallbackReply();
        }

        // tasks_to_add が3件超 → 切り捨て
        if (isset($json['tasks_to_add']) && count($json['tasks_to_add']) > 3) {
            $json['tasks_to_add'] = array_slice($json['tasks_to_add'], 0, 3);
        }

        // mood_guess が3値以外 → null
        if (!in_array($json['mood_guess'] ?? null, ['good', 'neutral', 'bad'], true)) {
            $json['mood_guess'] = null;
        }

        // bgm_key が4値以外 → null
        if (!in_array($json['bgm_key'] ?? null, ['calm', 'focus', 'refresh', 'sleep'], true)) {
            $json['bgm_key'] = null;
        }

        // tasks_to_complete は常に [] に強制
        $json['tasks_to_complete'] = [];

        // 医療用語チェック → フォールバック
        $medicalTerms = ['診断', '治療', '症状', 'うつ病', '障害', '投薬', '処方'];
        foreach ($medicalTerms as $term) {
            if (str_contains($json['reply'] ?? '', $term)) {
                $json['reply'] = self::getFallbackReply();
                break;
            }
        }

        return $json;
    }

    /**
     * Get fallback reply (when AI fails).
     */
    public static function getFallbackReply(): string
    {
        return '…申し訳ないにゃ。ちょっと調子が悪いみたいです。もう一度お試しください。';
    }

    private function writeLog(
        ?int $userId,
        int $latencyMs,
        int $tokensIn,
        int $tokensOut,
        bool $ok,
        ?string $errorMessage = null
    ): void {
        try {
            LlmLog::create([
                'user_id'        => $userId,
                'model'          => 'gpt-4o',
                'prompt_version' => self::PROMPT_VERSION,
                'tokens_in'      => $tokensIn,
                'tokens_out'     => $tokensOut,
                'cost_estimate'  => ($tokensIn * 0.0000025) + ($tokensOut * 0.00001),
                'latency_ms'     => $latencyMs,
                'ok'             => $ok,
                'error_message'  => $errorMessage,
            ]);
        } catch (\Exception $e) {
            // ログ保存失敗はサービス本体に影響させない
            Log::error('Failed to write LlmLog', ['message' => $e->getMessage()]);
        }
    }

    private function buildSystemPrompt(bool $allowTaskCompletion): string
    {
        $completeRule = $allowTaskCompletion
            ? 'タスク完了（tasks_to_complete）を提案できます。'
            : 'タスク完了（tasks_to_complete）は提案しないでください。';

        return <<<PROMPT
あなたは心身を整えるためのアドバイザー（猫キャラ）です。

## 役割
- 診断・治療はしない
- セルフケア（水を飲む，深呼吸，散歩など）を提案
- ユーザーの気分状態を観察してタスクを最大3つ提案
- **JSON のみを返す。説明やコードブロック記号は含めない**

## JSON形式（このオブジェクトだけを返す）

{
  "reply": "猫の返事（必須、日本語、親切な口調）",
  "mood_guess": "good|neutral|bad",
  "memory_summary": "会話の要点（1行、最大100文字程度）",
  "tasks_to_add": [
    {"title": "短いタスク", "reason": "なぜこのタスク"}
  ],
  "tasks_to_complete": [],
  "bgm_key": "calm|focus|refresh|sleep"
}

## ルール
- JSON オブジェクトだけを出力する。説明もコードブロック（```）も含めない
- タスク生成は最大3つ
- 各タスクのtitleは20-40文字程度
- メンタル的に有益で、小さく実行できる行動を優先
- 予定タスク（課題/バイト）とセルフケアを混ぜてよい
- memory_summary は会話の重要なポイントを1行で
- {$completeRule}
- tasks_to_complete は常に空[]にする（タスク完了は別途判定）
- 返答は自然な形で語尾ににゃをつける

PROMPT;
    }
}
