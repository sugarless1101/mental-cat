# security.md — Mental Cat セキュリティルール

## 機密ファイル・絶対コミット禁止

| ファイル | 理由 |
|----------|------|
| `.env` | `OPENAI_API_KEY` / `APP_KEY` を含む |
| `.env.local` / `.env.*.local` | 同上 |
| `storage/` 内の個人データ | ユーザー記録 |

- `git add .` は使わない。必ずファイルを指定してステージする
- `.gitignore` に `.env` が含まれていることを毎回確認する
- `OPENAI_API_KEY` は `.env` からのみ読む。コード中に書かない

---

## ハードコード禁止

以下をコード中に直接書くことを禁止する。

- APIキー・シークレット・パスワード
- DBの接続文字列
- 本番のURL・ドメイン
- ユーザーIDや固定のテストアカウント情報

→ すべて `config()` または `env()` 経由で参照する。

---

## アクセス制御

- **全クエリは `user_id` でスコープする**（他ユーザーのデータを返さない）
- `Task`, `ChatMessage`, `MoodLog` の取得・更新は必ず `Auth::id()` で所有者確認
- ゲスト（`$user === null`）は DB に書き込まない

---

## 入力バリデーション

- 気分値は `in:good,neutral,bad` のみ許可。それ以外は null に正規化
- BGMキーは `in:calm,focus,refresh,sleep` のみ許可
- チャット入力は最大長を設ける（要確認：現在の max_length 制限値）
- AIレスポンスのJSONパースは try-catch で囲む。パース失敗時はフォールバック

---

## 本番環境の禁止操作

- `php artisan migrate:fresh` — データが全消去される
- `php artisan migrate:fresh --seed` — 同上
- `APP_DEBUG=true` のまま本番デプロイしない
- `OPENAI_API_KEY` をログに出力しない

---

## 依存パッケージ

- `composer.lock` / `package-lock.json` をコミットに含める（再現性確保）
- パッケージの追加は必ず理由を明示してから行う
- 要確認：定期的な `composer audit` / `npm audit` の実施方針
