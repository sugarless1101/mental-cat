# UPDATE.md — プロジェクト健全性チェック & 適正化

このファイルは Codex / Claude Code への作業指示書。
「プロジェクトを点検して」と言われたらこの手順を実行する。

---

## 1. チェック項目

### 1-A. 仕様書・ドキュメントとの整合性

以下の md / ルールファイルと実装の乖離を確認する。

| 確認対象 | 照合先 |
|----------|--------|
| AIレスポンスのJSONキー・型 | `.claude/rules/SOUL.md` / `docs/SPEC.md` |
| 気分値が `good\|neutral\|bad` のみか | `.claude/rules/SOUL.md` |
| BGMキーが `calm\|focus\|refresh\|sleep` のみか | `.claude/rules/SOUL.md` |
| タスクが最大3件に制限されているか | `.claude/rules/SOUL.md` / `docs/FINAL.md` |
| `/` と `/app` のUI分離が守られているか | `docs/UI.md` / `docs/FINAL.md` |
| 医療用語・診断表現がコードやプロンプトに混入していないか | `.claude/rules/SOUL.md` / `.claude/rules/CAT.md` |
| ゲストユーザーがDBに書き込んでいないか | `CLAUDE.md` / `docs/FINAL.md` |
| `Api/ChatController.php` が新規実装の起点になっているか | `CLAUDE.md` |
| フォールバックが `getFallbackReply()` 経由か | `CLAUDE.md` |
| `/admin` のアクセス制御がドキュメントどおりか | `.claude/rules/ADMIN.md` / `docs/FINAL.md` / `docs/DEBUG.md` |
| `docs/DEBUG.md` に書かれた既知バグが現行コードでも再現するか | `docs/DEBUG.md` |
| `CLAUDE.md` / `docs/SETUP.md` / `composer.json` のコマンド説明がずれていないか | `CLAUDE.md` / `docs/SETUP.md` / `composer.json` |

---

### 1-B. セキュリティ

| 確認対象 | 基準 |
|----------|------|
| `.env` が `.gitignore` に含まれているか | `.gitignore` |
| APIキー・シークレットがコード中にハードコードされていないか | `.env.example` / `config/` / アプリ本体 |
| ユーザー個別画面・更新系クエリで `user_id` スコープが漏れていないか（Task / ChatMessage / MoodLog） | `docs/SPEC.md` |
| `env()` が `config/`・bootstrap 以外のアプリコードで直接使われていないか | Laravelの一般原則 |
| `APP_DEBUG` が非 local 前提の設定で `true` 固定になっていないか | `.env.example` / `config/app.php` |
| `APP_ADMIN_SECRET` や OpenAIキーの参照が `config()` / `env()` 経由に限定されているか | `config/services.php` / `config/app.php` / `AdminGuard` |

---

### 1-C. コード品質

| 確認対象 | 基準 |
|----------|------|
| N+1クエリが発生していないか（eager loading漏れ） | `CLAUDE.md` |
| AIレスポンスが `ok / error` 構造で返っているか | `CLAUDE.md` |
| 例外がすべてキャッチされているか（特にAI呼び出し） | `CLAUDE.md` |
| `OpenAIService.php`（レガシー）が新規実装から参照されていないか | `CLAUDE.md` |
| 気分再選択で既存 todo を不必要に破壊していないか | `docs/FINAL.md` / `docs/UI.md` |
| ゲストの `__start__` が認証済みフローと同じ意図で扱われているか | `docs/FINAL.md` / `CLAUDE.md` |
| メモリ圧縮や集計処理がリクエスト時間を不必要に悪化させていないか | `docs/SPEC.md` / 実装 |
| Laravel Pint でフォーマット違反がないか | `CLAUDE.md` |

---

### 1-D. 依存・バージョン

| 確認対象 | コマンド |
|----------|----------|
| PHPバージョンが8.2以上か | `php -v` |
| composerパッケージに既知の脆弱性がないか | `composer audit` |
| npmパッケージに既知の脆弱性がないか | `npm audit` |
| `composer.lock` / `package-lock.json` が追跡対象になっているか | `git ls-files composer.lock package-lock.json` |

---

### 1-E. テスト

| 確認対象 | 基準 |
|----------|------|
| テストが全件パスするか | `composer test` |
| `tests/Feature/` にHTTPテストがあるか | PestPHP形式 |
| `tests/Unit/` にユニットテストがあるか | PestPHP形式 |
| 既知バグに対する回帰テストがあるか | `docs/DEBUG.md` と `tests/` を照合 |
| UI変更時に主要導線のブラウザ確認があるか | Playwright / 手動確認の記録 |

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
- スクリーンショット、Playwright成果物、ローカルDBなどの生成物が誤って追跡されていないか

**レガシーコードの候補**
- `app/Http/Controllers/ChatController.php`（非Api/）— 参照がなければ削除推奨
- `app/Services/OpenAIService.php` — `MentalCatAiService` に完全移行済みなら削除推奨
- `resources/views/chat.blade.php` — 重複する新UIがないか確認

**設定・環境ファイルの残骸**
- `.env.example` に実態と乖離したキーが残っていないか
- `config/` に使われていない設定ファイルがないか
- `docs/SETUP.md` と `.env.example` のセットアップ手順が一致しているか

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
- 気分ログの集約ルール（同一1分以内は最新1件）が実装と一致しているか

**ルート**
- `routes/web.php` に存在しないコントローラーを指定しているルートがないか
- 認証ミドルウェア（`auth`）が必要なのに付いていないルートがないか
- 逆に `auth` が不要なのに付いているルートがないか

**環境・設定**
- `.env` に存在するキーが `.env.example` に抜けていないか（新メンバーがセットアップできない状態）
- `config/` 内での `env()` 使用は許容されているか、アプリ本体側では `config()` 経由になっているか
- ドキュメントのコマンド説明と実際のスクリプト定義が一致しているか

**ログ・ストレージ**
- `storage/logs/laravel.log` が異常に肥大化していないか
- `storage/app/` に不要なファイルが蓄積されていないか

**フロントエンド**
- Bladeテンプレートに直書きされたスタイル（`style=""`）が大量にないか
- Alpine.jsの `x-data` が巨大なインラインオブジェクトになっていないか（可読性の観点）
- コンソールエラーが発生するJSがないか（ビルド後の `npm run build` やブラウザ確認で把握）

---

## 2. 報告フォーマット

チェックで問題が見つかった場合、以下の優先度で分類して報告する。

| 優先度 | 意味 | 対応 |
|--------|------|------|
| **P1** | セキュリティリスク・データ漏洩の可能性 | 最優先で共有・必要なら即時修正 |
| **P2** | 仕様との明確な乖離・バグ | 早めに修正 |
| **P3** | コード全体にわたる修正が必要 | 計画的に対応 |
| **P4** | 部分的な修正で解決できる | 次の作業時に対応 |
| **P5** | 動作はしているが、より良い代替案がある | 余裕があれば対応 |
| **P0** | その他・情報共有のみ | 対応不要 |

報告例:
```
[P1] ユーザー個別更新APIで user_id スコープが漏れている
  → app/Http/Controllers/TaskController.php:18
[P2] 気分再選択で既存 todo が削除される
  → app/Http/Controllers/Api/ChatController.php:83
[P4] docs/SETUP.md の起動手順が composer.json の dev スクリプトとずれている
  → docs/SETUP.md:1, composer.json:45
```

---

## 3. 実行フェーズ

原則として、報告後にユーザーの承認を得てから修正を実行する。
ただし、秘密漏洩・認可欠如・誤公開などの **明白なP1** で、仕様変更を伴わない小さな修正は即時対応してよい。

### 実行順序
1. **P1** を最優先で共有し、必要なら即時修正する
2. **P2 / P3** はユーザーと優先順位を確認してから実行する
3. **P4 / P5** はまとめて一覧提示し、やるかどうかをユーザーが選ぶ
4. 修正後に `composer test` でテストが通ることを確認する
5. 必要に応じて UI の主要導線を確認する
6. 修正後に `./vendor/bin/pint` でフォーマット統一する

### 修正してはいけないこと
- ユーザーの依頼なしに `docs/` 以下の仕様内容を勝手に書き換えること
- `.claude/rules/` 以下のファイル（ルールはユーザーが管理）
- `database/migrations/` の既存ファイル（データ損失リスク）
- `composer.lock` / `package-lock.json` の手動編集
