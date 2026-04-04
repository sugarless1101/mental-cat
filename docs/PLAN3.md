# PLAN3.md — フェーズ3：体験の完成・ポリッシュ

目標：**「見せられる・使える・語れる」完成形にする**  
対象期間：1〜2日（PLAN2完了後）

---

## ゴール

- ユーザーが感情を委ねられる体験が完結している
- LLMOpsのデータがAdminページで可視化されている
- Playwrightで主要フローが自動検証されている

---

## タスク一覧

### [3-1] Adminページ（`/admin`）新設

LLMOpsの観測データを開発者・運用者向けに可視化する専用ページ。
ユーザー向けの `/app` とは完全分離。

```
新規: app/Http/Controllers/AdminController.php
新規: resources/views/pages/admin.blade.php
改修: routes/web.php（/admin ルート追加・admin ミドルウェア or 環境変数ガード）
```

表示内容：
```
LLM使用状況
  今月のトークン合計 / 推定コスト / 平均レスポンス時間 / 成功率

Guardrails発動ログ
  フォールバック発生回数 / 医療用語検出件数

ユーザー概況（任意）
  アクティブユーザー数 / 気分ログ件数 / タスク完了率
```

アクセス制御（簡易）：
- `APP_ADMIN_SECRET` 環境変数をベーシック認証代わりに使う、または
- ローカル環境（`APP_ENV=local`）のみアクセス許可

---

### [3-2] フィードバックUI（猫の返答に👍/👎）

猫の返答ごとに評価を保存し、プロンプト改善のサイクルを作る。

```
改修: database/migrations（llm_logs にfeedbackカラム追加 or 別テーブル）
改修: resources/views/pages/landing.blade.php（チャットバブルに👍/👎追加）
新規: app/Http/Controllers/Api/FeedbackController.php
新規: routes/api.php にルート追加
```

---

### [3-3] 猫アニメーションの状態連動

現状：CSS背景エフェクトのみ。猫の「状態」が視覚的に変わらない。

```
改修: resources/views/pages/landing.blade.php
```

実装方針：
- CSS クラスの切替で状態を表現（`cat--idle` / `cat--happy` / `cat--sad` / `cat--calm`）
- `mood_guess` を受け取ったタイミングでクラスを更新
- アニメーションはCSSのみで実装（JS依存を増やさない）

---

### [3-4] E2Eテスト（Playwright）

Playwright MCPを使って主要フローを自動検証する。

```
新規: tests/e2e/landing.spec.js（またはPlaywright MCPで都度確認）
```

確認するフロー：
1. トップページが表示される
2. 気分ボタンを押すと猫が返答する
3. チャット入力で会話が続く
4. タスクが表示・チェックできる
5. ログイン → ダッシュボードにアクセスできる
6. ダッシュボードで気分グラフが表示される

---

### [3-5] UIポリッシュ

#### 認証ページのデザイン統一
- Breezeデフォルトのlogin/registerビューをMental Catのトーンに合わせる
- 黒基調 + acccentカラーに統一

#### `/app` ダッシュボードの改善
- タスクにチェックボックスを追加（UIから完了操作可能に）
- 気分グラフのツールチップを日本語に

#### レスポンシブ対応
- スマートフォンでランディングページが崩れないか確認
- TaskWidget / BGMWidget のモバイルレイアウト調整

---

### [3-6] プロンプトバージョン管理（余裕があれば）

現状：`buildSystemPrompt()` がハードコード。

```
新規: app/Services/PromptRepository.php
改修: app/Services/MentalCatAiService.php
```

```php
// バージョン管理されたプロンプトを返す
class PromptRepository {
    public static function getActive(string $key): array {
        return [
            'version' => 'v2',
            'content' => '...',
        ];
    }
}
```

`llm_logs.prompt_version` に記録することでA/Bテストが可能になる。

---

## 完了条件

- [ ] `/app` にLLMコストパネルが表示される
- [ ] 猫の返答に👍/👎が付けられる
- [ ] 猫アニメーションが気分に応じて変わる
- [ ] Playwrightで主要フローが通る
- [ ] `php artisan test` が全件パス
- [ ] `composer audit` / `npm audit` が0件

---

## 最終チェックリスト（全フェーズ完了時）

- [ ] FINAL.mdの完成形と一致しているか
- [ ] `.env` がコミットされていないか
- [ ] `APP_DEBUG=false` になっているか（本番環境）
- [ ] レートリミットが有効か（`/api/chat`）
- [ ] 全テストがパスするか
- [ ] 脆弱性ゼロか（audit）
