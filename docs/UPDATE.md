# UPDATE.md — プロジェクト健全性チェック & 適正化

このファイルはClaude Codeへの作業指示書。
「プロジェクトを点検して」と言われたらこの手順を実行する。

---

## 1. チェック項目

### 1-A. 仕様書との整合性

以下のmdと実装の乖離を確認する。

| 確認対象 | 照合先 |
|----------|--------|
| AIレスポンスのJSONキー・型 | `SOUL.md` / `CLAUDE.md` |
| 気分値が `good\|neutral\|bad` のみか | `SOUL.md` |
| BGMキーが `calm\|focus\|refresh\|sleep` のみか | `SOUL.md` |
| タスクが最大3件に制限されているか | `SOUL.md` |
| `/` と `/app` のUI分離が守られているか | `UI.md` |
| 医療用語・診断表現がコードやプロンプトに混入していないか | `SOUL.md` / `CAT.md` |
| ゲストユーザーがDBに書き込んでいないか | `CLAUDE.md` |
| `Api/ChatController.php` が新規実装の起点になっているか | `CLAUDE.md` |
| フォールバックが `getFallbackReply()` 経由か | `CLAUDE.md` |

---

### 1-B. セキュリティ

| 確認対象 | 基準 |
|----------|------|
| `.env` が `.gitignore` に含まれているか | `security.md` |
| APIキー・シークレットがコード中にハードコードされていないか | `security.md` |
| 全クエリに `user_id` スコープがあるか（Task / ChatMessage / MoodLog） | `security.md` / `SPEC.md` |
| `config()` / `env()` 以外でAPIキーを参照していないか | `security.md` |
| `APP_DEBUG` が本番で `true` になっていないか | `security.md` |

---

### 1-C. コード品質

| 確認対象 | 基準 |
|----------|------|
| N+1クエリが発生していないか（eager loading漏れ） | `CLAUDE.md` |
| AIレスポンスが `ok / error` 構造で返っているか | `CLAUDE.md` |
| 例外がすべてキャッチされているか（特にAI呼び出し） | `CLAUDE.md` |
| `OpenAIService.php`（レガシー）が新規実装から参照されていないか | `CLAUDE.md` |
| Laravel Pint でフォーマット違反がないか | `CLAUDE.md` |

---

### 1-D. 依存・バージョン

| 確認対象 | コマンド |
|----------|----------|
| PHPバージョンが8.2以上か | `php -v` |
| composerパッケージに既知の脆弱性がないか | `composer audit` |
| npmパッケージに既知の脆弱性がないか | `npm audit` |
| `composer.lock` / `package-lock.json` がコミットされているか | `git status` |

---

### 1-E. テスト

| 確認対象 | 基準 |
|----------|------|
| テストが全件パスするか | `composer test` |
| `tests/Feature/` にHTTPテストがあるか | PestPHP形式 |
| `tests/Unit/` にユニットテストがあるか | PestPHP形式 |

---

### 1-F. 不要ファイル・デッドコード

以下の観点でプロジェクト全体をスキャンし、不要なものを洗い出す。

**不要ファイルの候補**
- どこからも `use` / `require` / `@include` されていないPHPクラス・Bladeファイル
- `routes/web.php` に対応するルートがないコントローラー
- 削除済み機能に紐づいたmigrationファイル（テーブルが存在しないのにファイルが残っている）
- `public/` や `storage/` に放置された古いビルド成果物
- コメントアウトされたまま長期間残っているコードブロック
- `console.log` / `dd()` / `dump()` / `var_dump()` のデバッグ出力の残留

**レガシーコードの候補**
- `app/Http/Controllers/ChatController.php`（非Api/）― 参照がなければ削除推奨
- `app/Services/OpenAIService.php` ― `MentalCatAiService` に完全移行済みなら削除推奨
- `resources/views/chat.blade.php` ― `partials/chat.blade.php` と重複していないか確認

**設定・環境ファイルの残骸**
- `.env.example` に実態と乖離したキーが残っていないか
- `config/` に使われていない設定ファイルがないか

**フロントエンド**
- `resources/js/` や `resources/css/` に未使用のファイルがないか
- `package.json` に未使用のnpmパッケージがないか

---

### 1-G. その他

明確なバグや仕様違反ではないが、放置すると後で困るものを拾う。

**Git**
- `git status` で意図せず追跡されているファイルがないか
- `.gitignore` に追加すべきファイルが漏れていないか
- コミットメッセージが極端に雑なコミットが連続していないか（参考情報として報告のみ）
- マージ済みのブランチが大量に残っていないか

**DB・モデル**
- `$fillable` / `$guarded` が定義されていないモデルがないか（マスアサインメント脆弱性）
- リレーションが定義されているのに対応するFKがmigrationにないか
- インデックスが貼られていない頻繁に検索されるカラムがないか（`user_id` / `status` / `created_at` など）

**ルート**
- `routes/web.php` に存在しないコントローラーを指定しているルートがないか
- 認証ミドルウェア（`auth`）が必要なのに付いていないルートがないか
- 逆に `auth` が不要なのに付いているルートがないか

**環境・設定**
- `.env` に存在するキーが `.env.example` に抜けていないか（新メンバーがセットアップできない状態）
- `config/` ファイルで `env()` を直接呼んでいる箇所がないか（`config()` 経由が正しい）

**ログ・ストレージ**
- `storage/logs/laravel.log` が異常に肥大化していないか
- `storage/app/` に不要なファイルが蓄積されていないか

**フロントエンド**
- Bladeテンプレートに直書きされたスタイル（`style=""`）が大量にないか
- Alpine.jsの `x-data` が巨大なインラインオブジェクトになっていないか（可読性の観点）
- コンソールエラーが発生するJSがないか（ビルド後の `npm run build` で警告確認）

---

## 2. 報告フォーマット

チェックで問題が見つかった場合、以下の優先度で分類して報告する。

| 優先度 | 意味 | 対応 |
|--------|------|------|
| **P1** | セキュリティリスク・データ漏洩の可能性 | 即時対応必須 |
| **P2** | 仕様との明確な乖離・バグ | 早めに修正 |
| **P3** | コード全体にわたる修正が必要 | 計画的に対応 |
| **P4** | 部分的な修正で解決できる | 次の作業時に対応 |
| **P5** | 動作はしているが、より良い代替案がある | 余裕があれば対応 |
| **P0** | その他・情報共有のみ | 対応不要 |

報告例:
```
[P1] MoodLog の取得に user_id スコープがない（他ユーザーのデータを返す可能性）
  → app/Http/Controllers/MoodController.php:42
[P4] OpenAIService.php が Api/ChatController から参照されている（レガシー呼び出し）
  → app/Http/Controllers/Api/ChatController.php:87
[P5] tasks() クエリで with() による eager loading が未使用
  → app/Http/Controllers/DashboardController.php:23
```

---

## 3. 実行フェーズ

報告後、ユーザーの承認を得てから修正を実行する。

### 実行順序
1. **P1** を全件修正（承認なしで即実行してよい）
2. **P2 / P3** はユーザーと優先順位を確認してから実行
3. **P4 / P5** はまとめて一覧提示し、やるかどうかをユーザーが選ぶ
4. 修正後に `composer test` でテストが通ることを確認
5. 修正後に `./vendor/bin/pint` でフォーマット統一

### 修正してはいけないこと
- `docs/` 以下のmdファイルの内容（仕様書はユーザーが管理）
- `.claude/rules/` 以下のファイル（ルールはユーザーが管理）
- `database/migrations/` の既存ファイル（データ損失リスク）
- `composer.lock` / `package-lock.json` の手動編集
