# PLAN1.md — フェーズ1：バグ修正・動作安定化

目標：**現状の動作品質を完成形の基盤レベルに引き上げる**  
対象期間：1日

---

## ゴール

- 既知のバグをすべて修正する
- 体験ページ（`/`）が仕様通りに動作する
- CDN依存をなくしてビルドを安定させる

---

## タスク一覧

### 必須（今日中）

#### [1-1] BGM切替を実装する
**問題**: `bgm_key` を受け取っているが BGM が切り替わらない  
**ファイル**: `resources/views/pages/landing.blade.php`

```js
// 追加するBGMマップ（URLは[1-2]で確定後に埋める）
const BGM_MAP = {
  calm:    'https://www.youtube.com/embed/XXXX',
  focus:   'https://www.youtube.com/embed/XXXX',
  refresh: 'https://www.youtube.com/embed/XXXX',
  sleep:   'https://www.youtube.com/embed/XXXX',
};

// sendChatMessage() のレスポンス処理に追加
function updateBgm(bgmKey) {
  const url = BGM_MAP[bgmKey];
  if (!url) return;
  const iframe = document.querySelector('.recommend_list iframe');
  if (iframe) iframe.src = url + '?autoplay=1';
}
```

#### [1-2] BGM URLマップを確定する
**問題**: bgm_key（calm/focus/refresh/sleep）に対応するYouTube URLが未定義  
**作業**: URLを4つ決めて `BGM_MAP` に設定する  
※ `docs/UI.md` に good/neutral/bad 別のURLがあるので参考にする

#### [1-3] N+1クエリを修正する
**問題**: `dashboard.blade.php` で `$log->user->name` を参照しているが `with('user')` が未設定  
**ファイル**: `app/Http/Controllers/DashboardController.php:21`

```php
// 修正前
$moodLogs = $user->moodLogs()->latest('logged_at')->limit(100)->get();

// 修正後
$moodLogs = $user->moodLogs()->with('user')->latest('logged_at')->limit(100)->get();
```

---

### 推奨（できれば今日中）

#### [1-4] Tailwind CDN → Viteビルドに移行する
**問題**: `layouts/guest.blade.php` が `cdn.tailwindcss.com` を使用している（本番非推奨・Purge不可）  
**ファイル**: `resources/views/layouts/guest.blade.php`

```html
<!-- 削除 -->
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { ... }</script>

<!-- 追加 -->
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

合わせて `tailwind.config.js` にゲストレイアウト用のカラー設定を移す。

#### [1-5] Chart.js を npm 化する
**問題**: `dashboard.blade.php` が `cdn.jsdelivr.net` から Chart.js を読み込んでいる  
**対応**:
```bash
npm install chart.js
```
`resources/js/` からimportしてViteでバンドル、またはBladeで `@vite` 経由で読み込む。

---

## 完了条件

- [ ] 気分を選ぶと BGM が切り替わる
- [ ] `/app` ダッシュボードで気分ログが N+1 なく表示される
- [ ] （推奨）CDN依存がなくなり `npm run build` のみで完結する
- [ ] `php artisan test` が全件パス
