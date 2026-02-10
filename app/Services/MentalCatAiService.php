<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MentalCatAiService
{
    private const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT = 30;

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        
        if (!$this->apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured');
        }
    }

    /**
     * Make a response from OpenAI based on user message and context
     *
     * @param string $userMessage - User's message
     * @param string $contextText - Context (long-term summary, recent messages, tasks)
     * @param bool $allowTaskCompletion - Whether to allow tasks_to_complete
     * @return array { ok: bool, reply?: string, json?: array, error?: string }
     */
    public function makeResponse(string $userMessage, string $contextText, bool $allowTaskCompletion = false): array
    {
        $systemPrompt = $this->buildSystemPrompt($allowTaskCompletion);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => "{$contextText}\n\nユーザー発言: {$userMessage}",
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
                ->timeout(self::TIMEOUT)
                ->post(self::OPENAI_API_URL, [
                    'model' => 'gpt-4o',
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 800,
                ])
                ->throw();

            $responseText = $response->json('choices.0.message.content');

            if (!$responseText) {
                return [
                    'ok' => false,
                    'error' => 'Empty response from OpenAI',
                ];
            }

            // Markdown コードブロック形式を処理
            $cleanedText = $responseText;
            if (preg_match('/```(?:json)?\s*\n(.*)\n```/s', $responseText, $matches)) {
                $cleanedText = $matches[1];
            }

            // JSONパース
            $json = json_decode($cleanedText, true);
            if (!is_array($json)) {
                Log::warning('Failed to parse OpenAI response as JSON', [
                    'original_response' => $responseText,
                    'cleaned_response' => $cleanedText,
                ]);

                return [
                    'ok' => false,
                    'error' => 'Invalid JSON response from AI',
                ];
            }

            return [
                'ok' => true,
                'reply' => $json['reply'] ?? null,
                'json' => $json,
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('OpenAI API request failed', [
                'status' => $e->response?->status(),
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'OpenAI API error: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error in MentalCatAiService', [
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build the system prompt based on SPEC.md
     */
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

    /**
     * Get fallback reply (when AI fails)
     */
    public static function getFallbackReply(): string
    {
        return '…申し訳ないにゃ。ちょっと調子が悪いみたいです。もう一度お試しください。';
    }
}
