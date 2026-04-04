# DEBUG.md — デバッグ計画

最終更新: 2026-04-04

---

## バグ一覧

| ID | 優先度 | タイトル | 状態 |
|----|--------|----------|------|
| D-7 | 🔴 高 | 通常チャットでAIが提案したタスクがUIに反映されない | ✅ 対応済 |
| D-1 | 🔴 高 | BGMが気分・会話に連動していない | ✅ 対応済 |
| D-2 | 🔴 高 | BGM URLマップが未定義・URLフォーマット不一致 | ✅ 対応済 |
| D-3 | 🟡 中 | 両レイアウトが Tailwind CDN に依存・Vite 未使用 | ✅ 対応済 |
| D-4 | 🟡 中 | Chart.js を CDN から読み込んでいる | ✅ 対応済 |
| D-5 | 🟡 中 | 気分ログ一覧にユーザー名が表示されている（仕様違反 + N+1） | ✅ 対応済 |
| D-6 | 🟢 低 | `.small` スタイルの本体が layouts 側にあり分散している | ✅ 対応済 |

---

## 詳細

---

### [D-7] 通常チャットでAIが提案したタスクがUIに反映されない 🔴

**症状**  
気分選択（`__start__`）直後はタスクが表示されるが、その後の通常チャットでAIがタスクを提案しても TaskWidget が更新されない。

**原因**  
`sendChatMessage()` の第3引数 `showRecommendation` が通常送信時に常に `false` で固定されている。

```js
// landing.blade.php:464
await sendChatMessage(text, currentMood, false); // ← 常にfalse

// landing.blade.php:391
if (showRecommendation) {
    mergeTaskStateFromPayload(data, true); // ← falseなので絶対呼ばれない
}
```

またバックエンドも `__start__` 以外では `Task::create()` を実行しないため、通常チャット由来のタスクはDBにも保存されない。

**影響**  
FINAL.md のデータフロー「チャット入力 → tasks_to_add → 追加提案」が完全に動いていない。コア体験の欠陥。

**修正内容**  
フロントとバックエンドの両方を直す必要がある。

フロント（`landing.blade.php`）：
```js
// 通常送信でもタスクをマージする
const data = await sendChatMessage(text, currentMood, false);
if (data?.tasks?.todo?.length) {
    mergeTaskStateFromPayload(data, false);
}
```

バックエンド（`Api/ChatController.php`）：  
`__start__` 以外のメッセージでも `tasks_to_add` があれば Task を保存するロジックを追加。

**影響ファイル**
- `resources/views/pages/landing.blade.php`
- `app/Http/Controllers/Api/ChatController.php`

---

### [D-1] BGMが気分・会話に連動していない 🔴

**症状**  
気分ボタンを押してもBGMが変わらない。AIが `bgm_key` を返しているが、フロントで無視されている。

**原因**  
`sendChatMessage()` のレスポンスから `bgm_key` を受け取っているが、BGMウィジェットの iframe の src を更新する処理がない。

**再現手順**
1. トップページを開く
2. 気分ボタンをどれか押す
3. BGMが変わらないことを確認（固定URLが流れ続ける）

**修正内容**  
`resources/views/pages/landing.blade.php` の JS に以下を追加：

```js
const BGM_MAP = {
  calm:    'https://www.youtube.com/embed/VIDEO_ID',
  focus:   'https://www.youtube.com/embed/VIDEO_ID',
  refresh: 'https://www.youtube.com/embed/VIDEO_ID',
  sleep:   'https://www.youtube.com/embed/VIDEO_ID',
};

function updateBgm(bgmKey) {
  const url = BGM_MAP[bgmKey];
  if (!url) return;
  const iframe = document.querySelector('.recommend_list iframe');
  if (iframe) iframe.src = url + '?autoplay=1';
}
```

`sendChatMessage()` のレスポンス処理に追加：
```js
if (data.bgm_key) updateBgm(data.bgm_key);
```

**影響ファイル**
- `resources/views/pages/landing.blade.php`

---

### [D-2] BGM URLマップが未定義・URLフォーマット不一致 🔴

**症状**  
`BGM_MAP` に入れるURLが未確定。また参照元URLをそのまま使うと動かない。

**原因①** URLが未定義  
`docs/UI.md` に good/neutral/bad 別のURLが3件あるが、AIが返す `bgm_key`（calm/focus/refresh/sleep）との対応が未定義。

**原因②** URLフォーマットが違う  
`docs/UI.md` に記載の候補URLは `youtu.be/VIDEO_ID?si=...` 形式（共有URL）。  
iframe の src には `youtube.com/embed/VIDEO_ID` 形式が必要。そのまま使うと埋め込みが動かない。

```
共有URL:  https://youtu.be/Lom67j9XqrU?si=xxx     ← iframe srcに使えない
埋め込み: https://www.youtube.com/embed/Lom67j9XqrU ← これが正しい
```

**修正内容**  
1. `bgm_key` に対応するYouTube動画を4つ選ぶ
2. 各動画の VIDEO_ID を抜き出して `BGM_MAP` に embed URL 形式で設定する

| bgm_key | 想定シーン | 設定するURL（embed形式） |
|---------|-----------|------------------------|
| `calm` | 落ち着き・neutral | 要確認 |
| `focus` | 作業・課題 | 要確認 |
| `refresh` | 気分転換・good | 要確認 |
| `sleep` | 疲れ・休息・bad | 要確認 |

**影響ファイル**
- `resources/views/pages/landing.blade.php`

---

### [D-3] 両レイアウトが Tailwind CDN に依存・Vite 未使用 🟡

**症状**  
`/`（guest.blade.php）と `/app`（app.blade.php）の**両方**が `cdn.tailwindcss.com` を使用しており、Vite が CSS を配信していない。

```html
<!-- layouts/guest.blade.php:9 -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- layouts/app.blade.php:8 -->
<script src="https://cdn.tailwindcss.com"></script>
```

`npm run build` は実質 CSS を生成していない。D-4 の Chart.js npm 化をしても `/app` でバンドルが読み込まれないため、D-3 と D-4 はセットで直す必要がある。

**修正内容**

1. `tailwind.config.js` にカラー・フォント設定を追加：
```js
theme: {
  extend: {
    colors: { dark: '#0A0A0A', graylight: '#D1D5DB', accent: '#B6A7F2' },
    fontFamily: { sans: ['"Noto Sans JP"', 'sans-serif'] },
  },
},
```

2. **両レイアウト**の CDN script タグを削除し、`@vite` に置き換える：
```html
{{-- layouts/guest.blade.php と layouts/app.blade.php の両方 --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

3. `package.json` の `@tailwindcss/vite: ^4.0.0`（v4用プラグイン）を削除し、v3に統一する：
```bash
npm uninstall @tailwindcss/vite
```

**影響ファイル**
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/app.blade.php`
- `tailwind.config.js`
- `package.json`

---

### [D-4] Chart.js を CDN から読み込んでいる 🟡

**症状**  
`pages/dashboard.blade.php:140` で `cdn.jsdelivr.net` から Chart.js を読み込んでいる。  
D-3 を直して Vite を有効にしてから実施すること。

**修正内容**

```bash
npm install chart.js
```

`resources/js/app.js` に追加：
```js
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
window.Chart = Chart;
```

`dashboard.blade.php` の CDN script タグを削除する（`@vite` は D-3 で追加済み）。

**影響ファイル**
- `resources/views/pages/dashboard.blade.php`
- `resources/js/app.js`
- `package.json`

---

### [D-5] 気分ログ一覧にユーザー名が表示されている（仕様違反 + N+1） 🟡

**症状①：仕様違反**  
`/app` は自分のデータしか表示しない画面なのに、気分ログの各行にユーザー名が表示されている。  
`UI.md` の仕様は「日時・気分」のみ。自分の名前を毎行見せる意味がない。

```html
{{-- dashboard.blade.php:104 --}}
<div class="font-medium">@if($log->user) {{ $log->user->name }} @else 不明 @endif</div>
```

**症状②：N+1クエリ**  
ユーザー名を出すために `$log->user` を参照しているが eager loading がなく、ログ件数分クエリが発生する。

**修正内容**  
ユーザー名の表示自体を削除する。N+1 も同時に解消される。

```blade
{{-- 修正後: ユーザー名行を削除し、日時と気分のみ --}}
<div class="flex items-center justify-between border-b border-white/10 py-2">
    <div class="text-xs text-gray-400">
        {{ $log->logged_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}
    </div>
    <div class="text-lg font-semibold">
        @switch($log->mood)
            @case('good') 😊 @break
            @case('neutral') 🙂 @break
            @case('bad') 😞 @break
        @endswitch
    </div>
</div>
```

`DashboardController` の `with('user')` も不要になるため追加しない。

**影響ファイル**
- `resources/views/pages/dashboard.blade.php`

---

### [D-6] `.small` スタイルの本体が layouts 側にあり分散している 🟢

**症状**  
気分パネルの `.small` スタイルが `layouts/guest.blade.php` と `pages/landing.blade.php` の2箇所に分散している。

**注意**  
`guest.blade.php` 側が**本体**（position/scale/transition/flex-direction/キャプション非表示）。  
`landing.blade.php` 側はボタン余白のみ。  
`guest.blade.php` を削除するだけでは縮小挙動が壊れる。

**修正内容**  
`guest.blade.php` の `.small` スタイルを `landing.blade.php` の `<style>` ブロックへ**移動**し、`guest.blade.php` 側を削除する。

```css
/* landing.blade.php の <style> に移動する内容 */
#mood-panel.small {
    position: absolute;
    left: 2rem;
    top: 6rem;
    transform: scale(0.75);
    transform-origin: left top;
    transition: all .4s ease;
    z-index: 10;
}
#mood-panel.small ul {
    flex-direction: row;
    gap: .75rem;
}
#mood-panel.small .cat-caption {
    display: none;
}
#mood-panel.small ul li button {
    padding: 0.5rem 0.75rem;
}
```

**影響ファイル**
- `resources/views/layouts/guest.blade.php`（スタイル削除）
- `resources/views/pages/landing.blade.php`（スタイル追加）

---

## 修正の優先順序

```
[D-7] 通常チャットのタスク反映（フロント + バックエンド）
  ↓
[D-2] BGM URL確定（embed形式で4つ用意）
  ↓
[D-1] BGM切替JS実装（30分）
  ↓
[D-5] 気分ログのユーザー名削除（5分）
  ↓
[D-3] 両レイアウトを Vite に移行（1〜2時間）
  ↓
[D-4] Chart.js npm化（D-3完了後・30分）
  ↓
[D-6] .small スタイル移動（30分）
```
