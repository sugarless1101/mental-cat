<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatStoreRequest;
use App\Models\ChatMessage;
use App\Models\LlmLog;
use App\Models\Task;
use App\Services\ChatContextBuilder;
use App\Services\MemoryCompactionService;
use App\Services\MentalCatAiService;
use App\Services\PromptInjectionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private ChatContextBuilder $contextBuilder;

    private ?MentalCatAiService $aiService = null;

    private MemoryCompactionService $compactor;

    public function __construct()
    {
        $this->contextBuilder = new ChatContextBuilder;
        $this->compactor = new MemoryCompactionService;
    }

    private function aiService(): MentalCatAiService
    {
        return $this->aiService ??= new MentalCatAiService;
    }

    public function store(ChatStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $message = $request->input('message');
        $mood = $this->normalizeMood($request->input('mood'));
        $aiMessage = $this->buildAiMessage($message, $mood);

        $isStart = $message === '__start__';

        // __start__ はシステム内部メッセージのためインジェクション検査対象外
        $injection = $isStart ? ['detected' => false, 'blocked' => false] : PromptInjectionDetector::inspect($message);

        // 高信頼のインジェクション攻撃はAI呼び出し前にブロック（トークン節約 + 安全性）
        if ($injection['blocked']) {
            Log::warning('Prompt injection blocked', [
                'user_id' => $user?->id,
                'score' => $injection['score'],
                'patterns' => $injection['patterns'],
            ]);

            return response()->json([
                'ok' => true,
                'reply' => '…にゃ？なんか変なことを聞かれた気がするけど、うまく答えられないにゃ。',
                'mood_guess' => null,
                'bgm_key' => null,
                'tasks' => ['todo' => [], 'done_recent' => []],
                'messages' => [],
            ], 200);
        }

        try {
            if (! $user) {
                return $this->handleGuestRequest($aiMessage, $mood, $isStart, $injection['detected']);
            }

            // ユーザーメッセージを先に保存
            ChatMessage::create([
                'user_id' => $user->id,
                'role' => 'user',
                'content' => $message,
                'mood' => $mood,
            ]);

            // AI呼び出しはトランザクション外（外部APIをロック保持中に呼ばない）
            $contextText = $this->appendMoodContext(
                $this->contextBuilder->build($user),
                $mood
            );
            $aiResponse = $this->aiService()->makeResponse($aiMessage, $contextText, false, $user->id, $injection['detected']);

            if (! $aiResponse['ok']) {
                Log::warning('AI response failed', ['error' => $aiResponse['error']]);

                return response()->json($this->fallbackPayload(), 200);
            }

            $json = $aiResponse['json'] ?? [];

            // AIレスポンスの書き込みのみをトランザクションで保護
            $response = DB::transaction(function () use ($user, $mood, $isStart, $json, $aiResponse) {
                $assistantMsg = ChatMessage::create([
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'content' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                    'mood' => $json['mood_guess'] ?? null,
                    'memory_summary' => $json['memory_summary'] ?? null,
                ]);

                // LlmLog に chat_message_id を紐付け（フィードバックUI用）
                if (! empty($aiResponse['llm_log_id'])) {
                    LlmLog::where('id', $aiResponse['llm_log_id'])
                        ->update(['chat_message_id' => $assistantMsg->id]);
                }

                $recommendationMessage = null;
                if ($isStart) {
                    // FINAL.md: 気分選択時の tasks_to_add は TaskWidget のみ・DB保存なし。
                    // 既存 todo タスクは一切削除しない。
                    $taskTitlesToAdd = $this->buildTaskTitlesToAdd($json, $mood, true);
                    $recommendationMessage = $this->buildRecommendationMessage($taskTitlesToAdd);
                    if ($recommendationMessage) {
                        ChatMessage::create([
                            'user_id' => $user->id,
                            'role' => 'assistant',
                            'content' => $recommendationMessage,
                            'mood' => $mood,
                        ]);
                    }

                    $suggestedTodos = collect($taskTitlesToAdd)->map(fn ($title) => [
                        'id' => null,
                        'title' => $title,
                        'status' => 'todo',
                    ])->values();

                    return response()->json([
                        'ok' => true,
                        'reply' => $assistantMsg->content,
                        'chat_message_id' => $assistantMsg->id,
                        'recommendation_message' => $recommendationMessage,
                        'mood_guess' => $json['mood_guess'] ?? null,
                        'bgm_key' => $json['bgm_key'] ?? null,
                        'tasks' => [
                            'todo' => $suggestedTodos,
                            'done_recent' => [],
                        ],
                        'messages' => [],
                    ], 200);
                } else {
                    // Regular chat: save AI-suggested tasks without replacing existing ones.
                    $taskTitlesToAdd = $this->buildTaskTitlesToAdd($json, $mood, false);
                    foreach ($taskTitlesToAdd as $title) {
                        Task::create([
                            'user_id' => $user->id,
                            'title' => $title,
                            'status' => 'todo',
                            'source' => 'ai',
                            'chat_message_id' => $assistantMsg->id,
                        ]);
                    }
                }

                return response()->json(array_merge([
                    'ok' => true,
                    'reply' => $assistantMsg->content,
                    'chat_message_id' => $assistantMsg->id,
                    'recommendation_message' => null,
                    'mood_guess' => $json['mood_guess'] ?? null,
                    'bgm_key' => $json['bgm_key'] ?? null,
                ], $this->buildStatePayload($user)), 200);
            });

            // メモリコンパクション: レスポンス送信後に実行してユーザーを待たせない
            app()->terminating(function () use ($user): void {
                try {
                    (new MemoryCompactionService)->compact($user);
                } catch (\Throwable $e) {
                    Log::warning('MemoryCompaction failed', ['message' => $e->getMessage()]);
                }
            });

            return $response;
        } catch (\Throwable $e) {
            Log::error('ChatController@store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json($this->fallbackPayload(), 200);
        }
    }

    public function state(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => true,
                'tasks' => [
                    'todo' => [],
                    'done_recent' => [],
                ],
                'messages' => [],
            ], 200);
        }

        return response()->json(array_merge([
            'ok' => true,
        ], $this->buildStatePayload($user)), 200);
    }

    private function handleGuestRequest(string $message, ?string $mood, bool $isStart = false, bool $injectionDetected = false): JsonResponse
    {
        try {
            $simpleContext = $this->appendMoodContext("ユーザーメッセージ: {$message}", $mood);
            $aiResponse = $this->aiService()->makeResponse($message, $simpleContext, false, null, $injectionDetected);

            if (! $aiResponse['ok']) {
                return response()->json($this->fallbackPayload(), 200);
            }

            $json = $aiResponse['json'] ?? [];
            $taskTitlesToAdd = $this->buildTaskTitlesToAdd($json, $mood, $isStart);
            $recommendationMessage = $isStart
                ? $this->buildRecommendationMessage($taskTitlesToAdd)
                : null;

            return response()->json([
                'ok' => true,
                'reply' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                'recommendation_message' => $recommendationMessage,
                'mood_guess' => $json['mood_guess'] ?? null,
                'bgm_key' => $json['bgm_key'] ?? null,
                'tasks' => [
                    'todo' => collect($taskTitlesToAdd)->map(fn ($title) => [
                        'id' => null,
                        'title' => $title,
                        'status' => 'todo',
                    ])->values(),
                    'done_recent' => [],
                ],
                'messages' => [],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Guest chat error', ['message' => $e->getMessage()]);

            return response()->json($this->fallbackPayload(), 200);
        }
    }

    private function fallbackPayload(): array
    {
        return [
            'ok' => false,
            'reply' => MentalCatAiService::getFallbackReply(),
            'mood_guess' => null,
            'bgm_key' => null,
            'tasks' => [
                'todo' => [],
                'done_recent' => [],
            ],
            'messages' => [],
        ];
    }

    private function normalizeMood(mixed $mood): ?string
    {
        if (! is_string($mood)) {
            return null;
        }

        return in_array($mood, ['good', 'neutral', 'bad'], true) ? $mood : null;
    }

    private function appendMoodContext(string $context, ?string $mood): string
    {
        if (! $mood) {
            return $context;
        }

        return $context."\n\n現在の気分: {$mood}";
    }

    private function buildAiMessage(string $message, ?string $mood): string
    {
        if ($message !== '__start__' || ! $mood) {
            return $message;
        }

        return "いまの気分は{$mood}です。気分に合わせた短い返答をして、メンタルに良いタスクを3つおすすめしてください。";
    }

    private function buildTaskTitlesToAdd(array $json, ?string $mood, bool $useFallback): array
    {
        $titles = collect(array_slice($json['tasks_to_add'] ?? [], 0, 3))
            ->map(function ($task) {
                if (! is_array($task)) {
                    return '';
                }

                return trim((string) ($task['title'] ?? ''));
            })
            ->filter()
            ->unique()
            ->values();

        if ($useFallback && $titles->count() < 3) {
            foreach ($this->fallbackTaskTitlesForMood($mood) as $fallbackTitle) {
                if ($titles->contains($fallbackTitle)) {
                    continue;
                }
                $titles->push($fallbackTitle);
                if ($titles->count() >= 3) {
                    break;
                }
            }
        }

        return $titles->take(3)->all();
    }

    private function fallbackTaskTitlesForMood(?string $mood): array
    {
        return match ($mood) {
            'bad' => [
                '深呼吸を1分だけして体の力を抜く',
                '白湯か水をゆっくり1杯飲む',
                '今の気持ちを一言だけメモに書く',
            ],
            'good' => [
                '軽くストレッチを2分して体をほぐす',
                '今日うれしかったことを1つ書き出す',
                '明日の自分を助ける小タスクを1つ終わらせる',
            ],
            default => [
                '背筋を伸ばして深呼吸を3回する',
                '5分だけ散歩かその場足踏みをする',
                '次の30分でやることを1つだけ決める',
            ],
        };
    }

    private function buildRecommendationMessage(array $titles): ?string
    {
        if (count($titles) === 0) {
            return null;
        }

        $lines = array_values(array_map(
            fn ($title, $i) => ($i + 1).'. '.$title,
            $titles,
            array_keys($titles)
        ));

        return "気分に合ったおすすめタスクにゃ:\n".implode("\n", $lines);
    }

    private function buildStatePayload($user): array
    {
        $todo = $user->tasks()->where('status', 'todo')->latest('created_at')->limit(10)->get();
        $doneRecent = $user->tasks()->where('status', 'done')->latest('done_at')->limit(10)->get();

        $latestMessages = $user->chatMessages()
            ->where('content', '!=', '__start__')
            ->where('content', 'not like', '[記憶%')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return [
            'tasks' => [
                'todo' => $todo->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                ])->values(),
                'done_recent' => $doneRecent->map(fn ($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                ])->values(),
            ],
            'messages' => $latestMessages->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
            ])->values(),
        ];
    }
}
