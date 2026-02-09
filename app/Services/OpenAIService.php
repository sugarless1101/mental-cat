<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function chat($messages)
    {
        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            Log::error('OPENAI_API_KEY is missing');
            return [
                'ok' => false,
                'reply' => '…設定が見つからないにゃ。（APIキー未設定）',
                'error' => 'OPENAI_API_KEY missing',
            ];
        }

        $url = 'https://api.openai.com/v1/chat/completions';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 300,
            'temperature' => 0.8,
        ]);

        if ($response->failed()) {
            Log::error('OpenAI API failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [
                'ok'    => false,
                'reply' => '…今はちょっと眠いにゃ。少し待っててほしいにゃ。',
                'error' => 'openai_failed',
                'status'=> $response->status(),
            ];
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            Log::error('OpenAI response has no content', ['json' => $json]);
            return [
                'ok'    => false,
                'reply' => '…返事がうまく作れなかったにゃ。',
                'error' => 'no_content',
            ];
        }

        return [
            'ok'    => true,
            'reply' => $content,
        ];
    }
}
