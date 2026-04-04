# Mental Cat — CLAUDE.md

気分を記録すると猫AIが返答しセルフケアタスクを最大3つ提案するメンタルヘルスWebアプリ。
詳細ルール → `.claude/rules/` を参照。

## スタック
Laravel 12 / PHP 8.2 / Vite 7 + TailwindCSS v3 + Alpine.js v3 / OpenAI gpt-4o / PestPHP v3 / SQLite

## コマンド
```bash
composer dev        # 開発サーバー全起動（serve / queue / pail / vite）
composer test       # テスト（config:clear → pest）
./vendor/bin/pint   # コードフォーマット
php artisan migrate # DB更新（migrate:fresh は開発のみ）
```

## 主要パス
- `app/Services/MentalCatAiService.php` — メインAI（gpt-4o、JSON応答）
- `app/Http/Controllers/Api/ChatController.php` — チャットAPI（新規実装はここ）
- `app/Services/ChatContextBuilder.php` — AIコンテキスト構築
- `routes/web.php` — ルート定義
- `resources/views/pages/` — 体験ページ(`/`)・管理ページ(`/app`)

## 注意
- `ChatController.php`（非Api/）はレガシー。触らない
- ゲスト時は `$user === null` → `handleGuestRequest()` に分岐
- AI失敗時は必ず `getFallbackReply()` を使う
