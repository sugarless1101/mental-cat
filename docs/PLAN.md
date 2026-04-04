# PLAN.md — 開発・デバッグ計画

最終更新: 2026-04-04

---

## 現状サマリ

| 画面 | 状態 |
|------|------|
| `/` 体験ページ | 動作中。チャット・気分選択・タスク表示 OK |
| `/app` 管理ページ | 動作中。気分ログ・タスク・チャット履歴・グラフ OK |
| 認証（Breeze） | 動作中 |
| AI（gpt-4o） | 動作中。JSON応答・フォールバック OK |
| LLMOps | **未実装**（`docs/LLMOPS.md` 参照） |

---

## デバッグ：既知の問題

### 🔴 高優先度

**[D-1] BGMが気分・会話に連動していない**
- 現状：`landing.blade.php` に YouTube URL がハードコードされており、`bgm_key` に応じた切り替えが未実装
- 期待：AIが返す `bgm_key`（calm / focus / refresh / sleep）でiframeのsrcを差し替える
- 対応ファイル：`resources/views/pages/landing.blade.php`
- 修正方法：`sendChatMessage()` のレスポンスから `bgm_key` を取得し、BGMウィジェットの src を JS で更新する

**[D-2] BGM YouTube URL が確定していない**
- 現状：`docs/UI.md` に good/neutral/bad 別URLがあるが、`bgm_key` との対応マップが未定義
- 対応：`bgm_key → YouTube URL` の定数マップをJSに定義する
  ```js
  const BGM_MAP = {
    calm:    'https://www.youtube.com/embed/...',
    focus:   'https://www.youtube.com/embed/...',
    refresh: 'https://www.youtube.com/embed/...',
    sleep:   'https://www.youtube.com/embed/...',
  };
  ```

### 🟡 中優先度

**[D-3] Tailwind を CDN から読み込んでいる（本番非推奨）**
- `layouts/guest.blade.php` で `<script src="https://cdn.tailwindcss.com">` を使用
- Viteビルド（`npm run build`）で生成される CSS に切り替えるべき
- 対応ファイル：`resources/views/layouts/guest.blade.php`

**[D-4] Chart.js を CDN から読み込んでいる**
- `pages/dashboard.blade.php` で `cdn.jsdelivr.net/npm/chart.js` を直接読み込み
- `npm install chart.js` して `resources/js/` からimportするか、Viteでバンドルする

**[D-5] `/app` ダッシュボードで気分ログの `user` リレーションを N+1 で取得している**
- `dashboard.blade.php` の気分ログ一覧で `$log->user->name` を参照
- `DashboardController` の `$moodLogs` クエリに `->with('user')` が未設定
- 対応ファイル：`app/Http/Controllers/DashboardController.php:21`

### 🟢 低優先度

**[D-6] `mood-panel.small` の CSS が `layouts/guest.blade.php` に直書き**
- スタイルが分散している。`landing.blade.php` 内の `<style>` と重複
- 統一するか、Tailwindクラスに置き換える

---

## 開発計画

### フェーズ1：デバッグ完了（優先）

| タスク | 対応 | 難易度 |
|--------|------|--------|
| [D-1] BGM切替をJS実装 | bgm_key → URL マップ → iframe src更新 | 低 |
| [D-2] BGM URLマップ確定 | URLを決めてコードに定数として書く | 低 |
| [D-5] N+1修正 | `->with('user')` 追加 | 低 |

### フェーズ2：LLMOps実装（インターン選考対策）

`docs/LLMOPS.md` の計画に従い以下を実装する。

#### 2-1. LLM呼び出しログ（Observability）

```
新規ファイル:
- database/migrations/xxxx_create_llm_logs_table.php
- app/Models/LlmLog.php
改修:
- app/Services/MentalCatAiService.php（呼び出し前後でログ記録）
```

保存する情報：
- `model`, `prompt_version`, `tokens_in`, `tokens_out`
- `latency_ms`, `cost_estimate`（トークン数 × 単価で算出）
- `ok`（成功/失敗）, `user_id`

#### 2-2. 出力バリデーション（Guardrails）

`MentalCatAiService` の JSON パース後に追加するだけ。

```php
// 追加するチェック
- reply が空 → フォールバック
- tasks_to_add が3件超 → 切り捨て（既存だが明示化）
- mood_guess が3値以外 → null正規化（既存だが明示化）
- reply に医療用語 → フォールバック
```

#### 2-3. 評価テスト（Evals）

```
新規ファイル:
- tests/Unit/AiResponseEvalTest.php
```

テスト内容：
- AIレスポンスのJSON構造が正しいか
- タスクが3件以内か
- mood_guess が valid か
- reply が空でないか

### フェーズ3：将来拡張（余裕があれば）

| 機能 | 概要 | 備考 |
|------|------|------|
| コストダッシュボード | `/app` にトークン使用量・費用を表示 | llm_logsテーブル完成後 |
| フィードバックUI | 猫の返答に👍/👎 | フロント改修あり |
| メモリコンパクション | 50件超で自動要約 | `SPEC.md` に仕様あり・未実装 |
| プロンプトバージョン管理 | `PromptRepository` でDB管理 | 優先度低 |
| E2Eテスト | Playwright MCPで体験フローをテスト | Playwright導入済み |

---

## 実装順序（推奨）

```
[D-5] N+1修正（5分）
  ↓
[D-2] BGM URL確定（URLを決める）
  ↓
[D-1] BGM切替JS実装（30分）
  ↓
[2-1] LLMログ実装（2時間）
  ↓
[2-2] Guardrails追加（1時間）
  ↓
[2-3] Evalテスト（1時間）
```

---

## 技術的負債（いつか対応）

- `layouts/guest.blade.php` の Tailwind CDN → Viteビルドに移行
- Chart.js の CDN → npm化
- CSS の分散（guest.blade.php + landing.blade.php に混在）
- `resources/views/auth/` の Breezeデフォルトビューをデザイン統一
