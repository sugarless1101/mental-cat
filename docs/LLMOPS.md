# LLMOps 実装計画

インターン選考対策として、1〜2日で実装するLLMOps要素。

## 実装する（3つ）

### 1. LLM呼び出しログ（Observability）

**変更ファイル**
- `database/migrations/xxxx_create_llm_logs_table.php`（新規）
- `app/Models/LlmLog.php`（新規）
- `app/Services/MentalCatAiService.php`（改修：呼び出し前後にログ記録）

**保存内容**
```
llm_logs:
  user_id, model, prompt_version,
  tokens_in, tokens_out, cost_estimate,
  latency_ms, ok(bool), error_message,
  created_at
```

---

### 2. 出力バリデーション（Guardrails）

**変更ファイル**
- `app/Services/MentalCatAiService.php`（改修：JSONパース後にバリデーション追加）

**チェック内容**
- `mood_guess` が `good|neutral|bad` 以外 → null に正規化
- `tasks_to_add` が3件超 → 切り捨て
- `reply` が空 → フォールバック
- 医療用語（診断・治療・症状 etc.）が `reply` に含まれる → フォールバック

---

### 3. 評価テスト（Evals）

**変更ファイル**
- `tests/Unit/AiResponseEvalTest.php`（新規）

**テスト内容**
- AIレスポンスのJSON構造が常に正しいか
- `tasks_to_add` が3件以内か
- `mood_guess` が3値のみか
- `reply` が空でないか
- 医療用語を含まないか

---

## 実装しない（理由つき）

| 項目 | 理由 |
|------|------|
| プロンプトバージョン管理 | Repository層の追加が必要。指示が曖昧になりやすく手戻りリスクあり |
| コストダッシュボード | UIとデータ集計の両方が必要。デザイン判断が多く丸投げしにくい |
| フィードバックUI | Alpine.jsフロント部分で指示コストが高い |

---

## 実装順序

1. LLM呼び出しログ（migration → Model → Service改修）
2. 出力バリデーション（同じファイルへの追加なので連続して進める）
3. 評価テスト（最後にまとめて生成）
