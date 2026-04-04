# FINAL.md — Mental Cat 最終完成形

最終更新: 2026-04-04

---

## コンセプト（変わらないもの）

> 猫（友人）と会話して癒されるなかで、メンタルヘルスに関する情報が受動的に蓄積・可視化される。

ユーザーはただ猫に話しかけるだけでいい。  
気分ログ・会話記録・タスク提案はすべて自動で行われる。

---

## 完成形の画面構成

### `/` 体験ページ

| 要素 | 完成状態 |
|------|----------|
| ヘッダー | ロゴ + ハンバーガーメニュー（ログイン / ダッシュボードへ） |
| MoodSelector | 初回は中央表示。選択後は左上に縮小固定。再選択可能 |
| CatAnimation | 中央配置。気分・会話に応じてアニメーション状態が変わる（idle / calm / happy / sad） |
| ChatInput | 画面下部。猫への唯一の入力UI |
| TaskWidget | 左側。AIが提案したタスク最大3件。チェックで完了確認フロー |
| BGMWidget | 右側。`bgm_key` に応じてYouTube埋め込みが自動切替（URLマップは `landing.blade.php` の `BGM_MAP` 定数で管理） |
| 背景エフェクト | 黒基調・円アニメーション。静かで没入感のある世界観 |

### `/app` 管理ページ（ユーザー向け）

| 要素 | 完成状態 |
|------|----------|
| 今日の気分 | 最新1件を絵文字で表示 |
| To-Doリスト | AI提案タスク 未完了上位5件 |
| 最近完了 | 直近の完了タスク一覧 |
| 最近の会話 | チャット履歴（閲覧のみ） |
| 気分推移グラフ | Chart.js による折れ線グラフ（good/neutral/bad を数値化） |
| 気分ログ一覧 | 日時・気分の縦並びリスト |

### `/admin` Adminページ（開発者・運用者向け）

ユーザーには見せない。LLMOpsの観測データを可視化する。

| 要素 | 完成状態 |
|------|----------|
| LLM使用状況 | 今月のトークン合計・推定コスト・平均レスポンス時間・成功率 |
| Guardrailsログ | フォールバック発生回数・医療用語検出件数 |
| アクセス制御 | `APP_ENV=local` のみ、または `APP_ADMIN_SECRET` によるガード |

詳細ルール → `.claude/rules/ADMIN.md`

---

## 完成形の技術構成

### バックエンド
- Laravel 12 / PHP 8.2
- 認証: Laravel Breeze
- AI: OpenAI gpt-4o（`MentalCatAiService`）
- DB: SQLite（開発）/ 本番は環境変数で切替

### フロントエンド
- **Viteでビルド**（CDN依存ゼロ）
- TailwindCSS v3（設定済みテーマ: dark / graylight / accent）
- Alpine.js v3（状態管理）
- Chart.js（npmインストール・バンドル済み）

### LLMOps層
- `llm_logs` テーブルによる全呼び出し記録
- 出力バリデーション（Guardrails）
- PestPHP による Eval テスト
- メモリコンパクション（50件超で自動要約）

### テスト
- Feature: Auth / Mood / Task / Chat
- Unit: AiResponse Evals
- E2E: Playwright MCP で主要フローを確認

---

## 完成形のデータフロー

```
ユーザー
  │
  ├─ 気分選択
  │    ├─ [認証済み] MoodLog保存 → AI呼び出し（__start__）
  │    └─ [ゲスト]   保存なし   → AI呼び出し（__start__）
  │                                    ↓
  │                                bgm_key → BGM切替
  │                                tasks_to_add → TaskWidget（メモリのみ・DB保存なし）
  │                                reply → ChatBubble
  │
  ├─ チャット入力
  │    ├─ [認証済み] AI呼び出し
  │    │               ↓
  │    │           ChatMessage保存（role=assistant, content=reply, memory_summary=...）
  │    │           tasks_to_add → Task保存 + TaskWidget更新
  │    └─ [ゲスト]  AI呼び出し
  │                    ↓
  │                reply → ChatBubble（DB保存なし）
  │
  └─ タスク確認（はい/いいえ）
       ├─ [認証済み] Task.status = done（DB更新）
       └─ [ゲスト]   フロントのみで完了表示（DB保存なし）
```

> ゲストユーザーはDBに一切保存しない。体験（チャット・BGM・タスク表示）は同じ。

---

## 完成形のLLMOpsフロー

```
AI呼び出し
  │
  ├─ 開始時刻記録
  ├─ プロンプト送信（prompt_version付き）
  ├─ レスポンス受信
  │   ├─ JSONパース
  │   ├─ Guardrailsバリデーション
  │   └─ 異常時フォールバック
  │
  └─ llm_logs記録
      - tokens_in / tokens_out / latency_ms
      - cost_estimate（$0.000X）
      - ok / error
```

---

## 完成形の非目標（やらないこと）

- 医療診断・治療の提供
- 多機能TODOアプリ化
- `/app` でのチャット入力・BGM再生
- 手動タスク追加UI
- リアルタイム通知・プッシュ通知
- モバイルアプリ化
