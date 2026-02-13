<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatStoreRequest;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Services\ChatContextBuilder;
use App\Services\MentalCatAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private ChatContextBuilder $contextBuilder;
    private MentalCatAiService $aiService;

    public function __construct()
    {
        $this->contextBuilder = new ChatContextBuilder();
        $this->aiService = new MentalCatAiService();
    }

    public function store(ChatStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $message = $request->input('message');
        $mood = $this->normalizeMood($request->input('mood'));
        $aiMessage = $this->buildAiMessage($message, $mood);

        try {
            if (!$user) {
                return $this->handleGuestRequest($aiMessage, $mood);
            }

            return DB::transaction(function () use ($user, $message, $mood, $aiMessage) {
                ChatMessage::create([
                    'user_id' => $user->id,
                    'role' => 'user',
                    'content' => $message,
                    'mood' => $mood,
                ]);

                $contextText = $this->appendMoodContext(
                    $this->contextBuilder->build($user),
                    $mood
                );

                // Task completion is handled by explicit UI confirmation flow.
                $aiResponse = $this->aiService->makeResponse($aiMessage, $contextText, false);

                if (!$aiResponse['ok']) {
                    Log::warning('AI response failed', ['error' => $aiResponse['error']]);
                    return response()->json($this->fallbackPayload(), 200);
                }

                $json = $aiResponse['json'] ?? [];
                $assistantMsg = ChatMessage::create([
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'content' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                    'mood' => $json['mood_guess'] ?? null,
                    'memory_summary' => $json['memory_summary'] ?? null,
                ]);

                $recommendationMessage = null;
                if ($message === '__start__') {
                    // Replace previous pending tasks with the new recommendation set.
                    Task::where('user_id', $user->id)
                        ->where('status', 'todo')
                        ->delete();

                    $taskTitlesToAdd = $this->buildTaskTitlesToAdd($json, $mood, true);
                    foreach ($taskTitlesToAdd as $title) {
                        Task::create([
                            'user_id' => $user->id,
                            'title' => $title,
                            'status' => 'todo',
                            'source' => 'ai',
                            'chat_message_id' => $assistantMsg->id,
                        ]);
                    }

                    $recommendationMessage = $this->buildRecommendationMessage($taskTitlesToAdd);
                    if ($recommendationMessage) {
                        ChatMessage::create([
                            'user_id' => $user->id,
                            'role' => 'assistant',
                            'content' => $recommendationMessage,
                            'mood' => $mood,
                        ]);
                    }
                }

                return response()->json(array_merge([
                    'ok' => true,
                    'reply' => $assistantMsg->content,
                    'recommendation_message' => $recommendationMessage,
                    'mood_guess' => $json['mood_guess'] ?? null,
                    'bgm_key' => $json['bgm_key'] ?? null,
                ], $this->buildStatePayload($user)), 200);
            });
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

        if (!$user) {
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

    private function handleGuestRequest(string $message, ?string $mood): JsonResponse
    {
        try {
            $simpleContext = $this->appendMoodContext("ユーザーメッセージ: {$message}", $mood);
            $aiResponse = $this->aiService->makeResponse($message, $simpleContext, false);

            if (!$aiResponse['ok']) {
                return response()->json($this->fallbackPayload(), 200);
            }

            $json = $aiResponse['json'] ?? [];
            $taskTitlesToAdd = $this->buildTaskTitlesToAdd($json, $mood, $message === '__start__');
            $recommendationMessage = $message === '__start__'
                ? $this->buildRecommendationMessage($taskTitlesToAdd)
                : null;

            return response()->json([
                'ok' => true,
                'reply' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                'recommendation_message' => $recommendationMessage,
                'mood_guess' => $json['mood_guess'] ?? null,
                'bgm_key' => $json['bgm_key'] ?? null,
                'tasks' => [
                    'todo' => collect($taskTitlesToAdd)->map(fn($title) => [
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
        if (!is_string($mood)) {
            return null;
        }

        return in_array($mood, ['good', 'neutral', 'bad'], true) ? $mood : null;
    }

    private function appendMoodContext(string $context, ?string $mood): string
    {
        if (!$mood) {
            return $context;
        }

        return $context . "\n\n現在の気分: {$mood}";
    }

    private function buildAiMessage(string $message, ?string $mood): string
    {
        if ($message !== '__start__' || !$mood) {
            return $message;
        }

        return "いまの気分は{$mood}です。気分に合わせた短い返答をして、メンタルに良いタスクを3つおすすめしてください。";
    }

    private function buildTaskTitlesToAdd(array $json, ?string $mood, bool $useFallback): array
    {
        $titles = collect(array_slice($json['tasks_to_add'] ?? [], 0, 3))
            ->map(function ($task) {
                if (!is_array($task)) {
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
            fn($title, $i) => ($i + 1) . '. ' . $title,
            $titles,
            array_keys($titles)
        ));

        return "気分に合ったおすすめタスクにゃ:\n" . implode("\n", $lines);
    }

    private function buildStatePayload($user): array
    {
        $latestTasks = $user->tasks()->latest('created_at')->limit(10)->get();
        $todo = $latestTasks->where('status', 'todo')->values();
        $doneRecent = $user->tasks()->where('status', 'done')->latest('done_at')->limit(10)->get();

        $latestMessages = $user->chatMessages()
            ->where('content', '!=', '__start__')
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return [
            'tasks' => [
                'todo' => $todo->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                ])->values(),
                'done_recent' => $doneRecent->map(fn($t) => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'status' => $t->status,
                ])->values(),
            ],
            'messages' => $latestMessages->map(fn($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
            ])->values(),
        ];
    }
}
