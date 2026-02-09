<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenAIService;

class ChatController extends Controller
{
    public function __construct(private OpenAIService $openAI) {}

    public function chat(Request $request)
    {
        $userMessage  = (string) $request->input('message', '');
        $mood         = $request->input('mood'); // "bad" | "neutral" | "good" | null

        $base = config('cat.persona.gentle.prompt', 'あなたは優しい黒猫です。語尾は「にゃ」。');
        $moodAdd = '';
        if ($mood && ($extra = config("cat.mood_to_prompt.$mood"))) {
            $moodAdd = "\n\n【トーン指示】".$extra;
        }
        $systemPrompt = $base . $moodAdd;

        // 初回挨拶トリガー: "__start__"
        if ($userMessage === '__start__') {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => "いまの気分は{$mood}です。挨拶から初めて最初の一言だけ、短く声をかけてください。問いを1つだけ添えてください。"],
            ];
        } else {
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ];
        }

        $result = $this->openAI->chat($messages);

        return response()->json([
            'reply'  => $result['reply'] ?? '…今は上手く話せないにゃ。',
            'ok'     => $result['ok'] ?? false,
            'error'  => $result['error'] ?? null,
            'status' => $result['status'] ?? null,
        ]);
    }

}
