<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatStoreRequest;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Services\ChatContextBuilder;
use App\Services\MentalCatAiService;
use Illuminate\Http\JsonResponse;
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

    /**
     * Store a newly created message and get AI response.
     */
    public function store(ChatStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $message = $request->input('message');

        try {
            // ゲストの場合はコンテキストなしで処理
            if (!$user) {
                return $this->handleGuestRequest($message);
            }

            return DB::transaction(function () use ($user, $message) {
                // 1. ユーザーのメッセージを保存
                $userMsg = ChatMessage::create([
                    'user_id' => $user->id,
                    'role' => 'user',
                    'content' => $message,
                    'mood' => null,
                ]);

                // 2. コンテキスト構築
                $contextText = $this->contextBuilder->build($user);

                // 5. "やったよ"判定（誤爆防止）
                $allowTaskCompletion = $this->shouldAllowTaskCompletion($message);

                // 4. OpenAI に投げる
                $aiResponse = $this->aiService->makeResponse($message, $contextText, $allowTaskCompletion);

                if (!$aiResponse['ok']) {
                    Log::warning('AI response failed', ['error' => $aiResponse['error']]);

                    return response()->json([
                        'ok' => false,
                        'reply' => MentalCatAiService::getFallbackReply(),
                        'mood_guess' => null,
                        'bgm_key' => null,
                        'tasks' => [
                            'todo' => [],
                            'done_recent' => [],
                        ],
                        'messages' => [],
                    ], 200);
                }

                $json = $aiResponse['json'] ?? [];

                // 5. assistant の返事を保存
                $assistantMsg = ChatMessage::create([
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'content' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                    'mood' => $json['mood_guess'] ?? null,
                    'memory_summary' => $json['memory_summary'] ?? null,
                ]);

                // 7. tasks_to_add を INSERT（最大3）
                $tasksToAdd = array_slice($json['tasks_to_add'] ?? [], 0, 3);
                foreach ($tasksToAdd as $taskData) {
                    if (!empty($taskData['title'])) {
                        Task::create([
                            'user_id' => $user->id,
                            'title' => $taskData['title'],
                            'status' => 'todo',
                            'source' => 'ai',
                            'chat_message_id' => $assistantMsg->id,
                        ]);
                    }
                }

                // 8. tasks_to_complete があれば最新 todo を 1 件 done に
                $tasksToComplete = $json['tasks_to_complete'] ?? [];
                if ($allowTaskCompletion && !empty($tasksToComplete)) {
                    $todoTask = $user->tasks()
                        ->where('status', 'todo')
                        ->latest('created_at')
                        ->first();

                    if ($todoTask) {
                        $todoTask->update([
                            'status' => 'done',
                            'done_at' => now(),
                        ]);
                    }
                }

                // 9. 最新データを返す
                $latestTasks = $user->tasks()
                    ->latest('created_at')
                    ->limit(10)
                    ->get();

                $latestMessages = $user->chatMessages()
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                    ->reverse();

                // グルーピング：todo と最近完了済み(done_recent)
                $todo = $latestTasks->where('status', 'todo')->values();
                $doneRecent = $user->tasks()
                    ->where('status', 'done')
                    ->latest('done_at')
                    ->limit(10)
                    ->get();

                return response()->json([
                    'ok' => true,
                    'reply' => $assistantMsg->content,
                    'mood_guess' => $json['mood_guess'] ?? null,
                    'bgm_key' => $json['bgm_key'] ?? null,
                    'tasks' => [
                        'todo' => $todo->map(fn($t) => [
                            'id' => $t->id,
                            'title' => $t->title,
                            'status' => $t->status,
                        ]),
                        'done_recent' => $doneRecent->map(fn($t) => [
                            'id' => $t->id,
                            'title' => $t->title,
                            'status' => $t->status,
                        ]),
                    ],
                    'messages' => $latestMessages->map(fn($m) => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'content' => $m->content,
                    ]),
                ], 200);
            });
        } catch (\Exception $e) {
            Log::error('ChatController@store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'reply' => MentalCatAiService::getFallbackReply(),
                'mood_guess' => null,
                'bgm_key' => null,
                'tasks' => [
                    'todo' => [],
                    'done_recent' => [],
                ],
                'messages' => [],
            ], 200);
        }
    }

    /**
     * Handle guest request (no user context)
     */
    private function handleGuestRequest(string $message): JsonResponse
    {
        try {
            // コンテキストなしで AI を呼び出す
            $simpleContext = "ユーザー初回メッセージ: {$message}";
            $allowTaskCompletion = $this->shouldAllowTaskCompletion($message);
            
            $aiResponse = $this->aiService->makeResponse($message, $simpleContext, $allowTaskCompletion);

            if (!$aiResponse['ok']) {
                return response()->json([
                    'ok' => false,
                    'reply' => MentalCatAiService::getFallbackReply(),
                    'mood_guess' => null,
                    'bgm_key' => null,
                    'tasks' => [
                        'todo' => [],
                        'done_recent' => [],
                    ],
                    'messages' => [],
                ], 200);
            }

            $json = $aiResponse['json'] ?? [];

            return response()->json([
                'ok' => true,
                'reply' => $json['reply'] ?? MentalCatAiService::getFallbackReply(),
                'mood_guess' => $json['mood_guess'] ?? null,
                'bgm_key' => $json['bgm_key'] ?? null,
                'tasks' => [
                    'todo' => [],
                    'done_recent' => [],
                ],
                'messages' => [],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Guest chat error', ['message' => $e->getMessage()]);
            
            return response()->json([
                'ok' => false,
                'reply' => MentalCatAiService::getFallbackReply(),
            ], 200);
        }
    }

    /**
     * Check if user message contains completion keywords
     *
     * @param string $message
     * @return bool
     */
    private function shouldAllowTaskCompletion(string $message): bool
    {
        $keywords = ['やった', '終わった', 'できた', '完了', '済み', 'やってた', 'やり終わった'];

        foreach ($keywords as $keyword) {
            if (mb_strpos($message, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, ChatMessage $chatMessage)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChatMessage $chatMessage)
    {
        //
    }
}
