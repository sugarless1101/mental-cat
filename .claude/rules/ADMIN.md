# ADMIN.md — Adminページの仕様・ルール

## このページは何者か

`/admin` は **開発者・運用者専用のLLMOps観測画面**。

- ユーザーには存在を知らせない
- ユーザー向けの `/app` とは目的・トーン・データが完全に別
- 「システムが健全に動いているか」を確認するための場所
- **UI.md の画面分離ルール（/ と /app の2画面）は /admin には適用しない**。  
  /admin は内部ツールであり、UIのトーン・デザイン規則の対象外。

---

## アクセス制御（絶対に守る）

### 基本方針

- **local 環境のみ**：`APP_ENV=local` なら無条件アクセス可
- **local 以外の全環境**（production / staging / preview / testing 等）：  
  `APP_ADMIN_SECRET` 環境変数が設定されており、かつリクエストの `X-Admin-Secret` ヘッダーと一致する場合のみアクセス可
- **フェイルクローズド**：`APP_ADMIN_SECRET` が未設定（空）の場合は local 以外で常に 403 を返す

> 「ベーシック認証」ではなく **シークレットヘッダー認証** を採用する。  
> HTTP Basic Auth との混同を避けるため、用語を統一する。

### 実装コード例（AdminController または Middleware）

```php
// local 以外のすべての環境でガード
if (!app()->environment('local')) {
    $secret = env('APP_ADMIN_SECRET'); // config() ではなく env() で直接取得
    if (empty($secret) || request()->header('X-Admin-Secret') !== $secret) {
        abort(403);
    }
}
```

**注意**：`config('app.admin_secret')` は使わない。  
`config/app.php` に `admin_secret` キーを追加しない限り `null` を返し、  
`APP_ADMIN_SECRET` 未設定時に `null !== null = false` となって fail-open になる。

### 環境変数

```
APP_ADMIN_SECRET=（ランダムな長文字列）
```

`.env.example` に空値でキーだけ追加しておく。

---

## 実装構成

```
新規: app/Http/Controllers/AdminController.php
新規: app/Http/Middleware/AdminGuard.php
新規: resources/views/pages/admin.blade.php
改修: bootstrap/app.php（ミドルウェア登録）
改修: routes/web.php（/admin ルート追加）
```

### bootstrap/app.php への登録

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'admin.guard' => \App\Http\Middleware\AdminGuard::class,
    ]);
})
```

### AdminGuard.php

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminGuard
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('local')) {
            $secret = env('APP_ADMIN_SECRET');
            if (empty($secret) || $request->header('X-Admin-Secret') !== $secret) {
                abort(403);
            }
        }

        return $next($request);
    }
}
```

### routes/web.php

```php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('admin.guard')
    ->name('admin');
```

---

## 表示する情報

### LLM使用状況（`llm_logs` から集計）

| 指標 | 集計方法 |
|------|----------|
| 今月のトークン合計 | `SUM(tokens_in + tokens_out)` WHERE 今月 |
| 推定コスト | `SUM(cost_estimate)` WHERE 今月 |
| 平均レスポンス時間 | `AVG(latency_ms)` |
| 成功率 | `COUNT(ok=true) / COUNT(*)` |
| 呼び出し回数 | `COUNT(*)` |

### Guardrailsログ

> **注意**：`llm_logs.ok` は汎用の成功/失敗フラグ（タイムアウト・ネットワーク失敗も含む）。  
> 「Guardrails でフォールバックした回数」を正確に計測するには、  
> `llm_logs` に `fallback_reason` カラム（例：`guardrails_invalid_json` / `guardrails_medical_term` / `timeout` / `api_error`）を追加し、区別する必要がある。  
> 現状未実装のため、以下は設計目標として記載する。

| 指標 | 内容 | 実装状態 |
|------|------|----------|
| フォールバック発生回数 | `COUNT(ok=false)` ※全失敗を含む概算値 | 計測可（精度低） |
| Guardrails 起因のフォールバック | `COUNT(fallback_reason LIKE 'guardrails_%')` | 要 `fallback_reason` カラム追加 |
| 医療用語検出件数 | `COUNT(fallback_reason = 'guardrails_medical_term')` | 未設計 |

### ユーザー概況（集計値のみ・個人特定不可）

| 指標 | 内容 |
|------|------|
| 総ユーザー数 | `COUNT(users)` |
| 今日のアクティブ | 今日チャットしたユーザー数 |
| 気分ログ総数 | `COUNT(mood_logs)` |

---

## 表示しない情報

- 特定ユーザーの気分ログ・チャット内容・タスク内容
- メールアドレス・パスワード関連
- `OPENAI_API_KEY` その他の秘密情報

---

## UIのトーン

- `/` や `/app` のデザインに合わせる必要はない
- シンプルなテーブル・数値羅列で十分
- 装飾よりも**情報の正確さと読みやすさ**を優先
- ダークテーマでも白テーマでも可

---

## 将来拡張（余裕があれば）

- `llm_logs` に `fallback_reason` カラムを追加して Guardrails の内訳を正確に計測
- 医療用語検出ロジックの実装と `fallback_reason = 'guardrails_medical_term'` での記録
- プロンプトバージョン別の成功率比較（A/Bテスト的な用途）
- Guardrails発動パターンの可視化
- 日次・週次のコスト推移グラフ
