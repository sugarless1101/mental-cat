# PLAN2.md — フェーズ2：LLMOps実装

目標：**インターン選考でアピールできるLLMOpsの実装**  
対象期間：1〜2日（PLAN1完了後）  
参照: `docs/LLMOPS.md`

---

## ゴール

- LLMの呼び出しをすべて記録できる（Observability）
- AIレスポンスの品質を自動チェックできる（Guardrails + Evals）
- 記録したデータをダッシュボードで確認できる（Cost visibility）

---

## タスク一覧

### [2-1] LLM呼び出しログ（最優先・LLMOpsの核心）

#### DBマイグレーション作成
```
新規: database/migrations/xxxx_create_llm_logs_table.php
```

```php
Schema::create('llm_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
    $table->string('model')->default('gpt-4o');
    $table->string('prompt_version')->default('v1');
    $table->integer('tokens_in')->default(0);
    $table->integer('tokens_out')->default(0);
    $table->decimal('cost_estimate', 10, 6)->default(0);
    $table->integer('latency_ms')->default(0);
    $table->boolean('ok')->default(true);
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index('user_id');
    $table->index('created_at');
});
```

#### Modelを作成
```
新規: app/Models/LlmLog.php
```

#### MentalCatAiServiceを改修
```
改修: app/Services/MentalCatAiService.php
```

`makeResponse()` の前後に計測・記録処理を追加：
```php
$startedAt = microtime(true);

// ... 既存のAPI呼び出し ...

$latencyMs = (int) ((microtime(true) - $startedAt) * 1000);
$tokensIn  = $response->json('usage.prompt_tokens') ?? 0;
$tokensOut = $response->json('usage.completion_tokens') ?? 0;

LlmLog::create([
    'user_id'        => $userId,
    'model'          => 'gpt-4o',
    'prompt_version' => 'v1',
    'tokens_in'      => $tokensIn,
    'tokens_out'     => $tokensOut,
    'cost_estimate'  => ($tokensIn * 0.0000025) + ($tokensOut * 0.00001),
    'latency_ms'     => $latencyMs,
    'ok'             => true,
]);
```

---

### [2-2] 出力バリデーション（Guardrails）

```
改修: app/Services/MentalCatAiService.php
```

JSONパース後に以下を追加する：

```php
private function validateAndSanitize(array $json): array
{
    // replyが空 → フォールバック
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

    // tasks_to_complete は常に [] に強制（UI確認フローで完了判定するため）
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
```

---

### [2-3] 評価テスト（Evals）

```
新規: tests/Unit/AiResponseEvalTest.php
```

```php
it('replyは必ず文字列で返る', function () { ... });
it('tasks_to_addは最大3件', function () { ... });
it('tasks_to_completeは常に空配列', function () { ... }); // UI確認フローで完了判定するため
it('mood_guessは3値のみ', function () { ... });
it('bgm_keyは4値のみ', function () { ... });
it('医療用語を含まない', function () { ... });
it('JSONとしてパース可能', function () { ... });
```

---

### [2-4] メモリコンパクション（SPEC.mdの仕様を実装）

```
新規: app/Services/MemoryCompactionService.php
```

発動条件：
- `chat_messages` が50件超 → 古い30件を要約して `memory_summary` に保存・削除
- `memory_summary` 件数が10件超 → 古い5件を再要約して1件に置換

呼び出しタイミング: `Api/ChatController::store()` の最後に非同期で実行

---

## 完了条件

- [ ] AI呼び出しのたびに `llm_logs` に記録される
- [ ] AIが不正な応答を返してもフォールバックされる
- [ ] Eval テストが全件パスする
- [ ] （推奨）メモリが50件超で自動圧縮される
- [ ] `php artisan test` が全件パス
