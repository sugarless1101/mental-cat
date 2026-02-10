<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Mental Cat Portal</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Tailwindカスタムテーマ：モノトーン＋アクセントカラー -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            dark: '#0A0A0A',
            graylight: '#D1D5DB',
            accent: '#B6A7F2',
          },
          fontFamily: {
            sans: ['"Noto Sans JP"', 'sans-serif'],
          },
        },
      },
    };
  </script>
    <style>
        #mood-panel.small{
            position:absolute;
            left: 2rem;      /* ← ここで左寄せ位置を調整 */
            top: 6rem;       /* ← 上下位置。task_listの少し上/横などに合わせて */
            transform:scale(0.75);
            transform-origin: left top; /* ← 左上を基点に縮小 */
            transition: all .4s ease;
            z-index: 10;     /* 他要素に埋もれないように */
        }

        /* 小さくなっても横並びのまま */
        #mood-panel.small ul{
            flex-direction: row;  /* ← columnにしていたのをrowに戻す */
            gap: .75rem;
        }

        /* 小さくなった時はキャプションを隠す */
        #mood-panel.small .cat-caption{
            display:none;
        }
        /* 常にクリック可能にしておく */
        #mood-panel{ z-index: 60; pointer-events: auto; }
        #mood-panel button{ cursor: pointer; padding: .4rem .6rem; border-radius: .5rem; pointer-events: auto; }

        /* 縮小時でもタッチしやすいようヒット領域を広げる */
        #mood-panel.small ul li button{ padding: .5rem .75rem; }
        /* タスクや他要素に覆われる場合に備え、常に上に出す */
        .task_list{ z-index: 10; }
    </style>

</head>

<body class="bg-dark text-graylight min-h-screen flex flex-col justify-between font-sans">
  <!-- header -->
  <header class="flex justify-between items-center p-4 bg-white/5 border-b border-white/10">
    <p class="text-xl tracking-wide">Mental Cat</p>
    <button class="text-graylight hover:text-accent transition" aria-label="menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </header>

  <!-- main -->
  <main class="flex flex-1 justify-center items-center relative p-4">
    <!-- 左：タスクリスト -->
    <div class="task_list absolute left-8 top-1/2 -translate-y-1/2 w-1/5 bg-white/5 rounded-xl p-4 text-sm text-gray-300 shadow-md border border-white/10">
      <p class="text-accent mb-3 font-semibold tracking-widest">TASK</p>
      <ul class="space-y-3">
        <li class="flex justify-between items-center">
          <span>深呼吸を3回</span>
          <input type="checkbox" class="w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded">
        </li>
        <li class="flex justify-between items-center">
          <span>お水を飲む</span>
          <input type="checkbox" class="w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded">
        </li>
        <li class="flex justify-between items-center">
          <span>好きな音楽を聴く</span>
          <input type="checkbox" class="w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded">
        </li>
      </ul>
    </div>

        <!-- 気分ボタン群 -->
        <div id="mood-panel" class="mental_button flex flex-col items-center space-y-8 transition-all duration-700">
            <ul id="mood-buttons" class="flex space-x-6 text-4xl">
                <li><button data-mood="bad" class="hover:scale-110 transition" aria-label="bad">😞</button></li>
                <li><button data-mood="neutral" class="hover:scale-110 transition" aria-label="neutral">🙂</button></li>
                <li><button data-mood="good" class="hover:scale-110 transition" aria-label="good">😊</button></li>
            </ul>
            <div class="mt-12 text-center">
                <p class="cat-caption text-gray-400 text-lg italic animate-pulse">
                🐈‍⬛ ...猫が静かに歩いている...
                </p>
            </div>
        </div>


    <!-- 右：おすすめ -->
    <div class="recommend_list absolute right-8 top-1/2 -translate-y-1/2 w-1/4 text-center bg-white/5 rounded-xl p-4 shadow-md border border-white/10">
      <ul class="space-y-4">
        <li>
          <iframe
            class="rounded-lg shadow-sm"
            width="100%" height="200"
            src="https://www.youtube.com/embed/nJktCw68UDo?si=FxJvdYa9KU6KePID"
            title="YouTube video player" frameborder="0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen>
          </iframe>
        </li>
        <li class="flex justify-center space-x-6 text-xl">
          <a href="https://x.com" target="_blank" aria-label="X (Twitter)" class="hover:text-accent transition">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
          <a href="https://www.instagram.com" target="_blank" aria-label="Instagram" class="hover:text-accent transition">
            <i class="fa-brands fa-instagram"></i>
          </a>
        </li>
      </ul>
    </div>
  </main>

  <!-- chat -->
  <div class="chat fixed bottom-4 left-1/2 -translate-x-1/2 w-3/4 max-w-2xl bg-white/5 rounded-xl p-3 border border-white/10 backdrop-blur-md">
    <!-- メッセージ表示領域 -->
    <div id="chat-box" class="h-56 overflow-y-auto mb-3 space-y-2 pr-1">
      <!-- ここに吹き出しが追加されます -->
    </div>

    <form id="chat-form" class="flex items-center gap-3">
      <input
        id="message"
        type="text"
        placeholder="猫に話しかけてみる…"
        class="flex-1 bg-transparent border-b border-gray-600 text-gray-200 focus:outline-none focus:border-accent p-2 placeholder-gray-500">
      <button
        id="send-btn"
        type="submit"
        class="bg-accent text-dark rounded-full w-10 h-10 flex items-center justify-center hover:bg-graylight transition"
        aria-label="send">
        ↑
      </button>
    </form>
  </div>

  <!-- Chat JS -->
  <script>
  const API_CHAT = "{{ url('/api/chat') }}";
  const API_PING = "{{ url('/api/ping') }}";

  const chatBox   = document.getElementById('chat-box');
  const form      = document.getElementById('chat-form');
  const input     = document.getElementById('message');
  const sendBtn   = document.getElementById('send-btn');

  const moodPanel = document.getElementById('mood-panel');
  const moodChip  = document.getElementById('mood-chip');
  const moodChipText = document.getElementById('mood-chip-text');

  let currentMood = null;

  // セッション設定
  const SESSION_KEY = 'cc_session_start';
  const GREET_KEY = 'cc_session_greeted';
  const SESSION_TTL = 1000 * 60 * 60; // 1時間

  function isSessionExpired() {
    const s = parseInt(localStorage.getItem(SESSION_KEY) || '0', 10);
    if (!s) return true;
    return (Date.now() - s) > SESSION_TTL;
  }

  function startNewSession() {
    const id = Date.now();
    localStorage.setItem(SESSION_KEY, String(id));
    localStorage.removeItem(GREET_KEY);
    return id;
  }

  // 吹き出し
  function addBubble(text, side = 'left') {
    const wrap = document.createElement('div');
    wrap.className = side === 'right' ? 'text-right' : 'text-left';
    const bubble = document.createElement('span');
    bubble.className = 'inline-block max-w-[85%] px-3 py-2 rounded-lg ' +
      (side === 'right'
        ? 'bg-white/20 border border-white/20'
        : 'bg-black/30 border border-white/10');
    bubble.textContent = text;
    wrap.appendChild(bubble);
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
  }

  // タイピング
  let typingEl = null;
  function showTyping() {
    typingEl = document.createElement('div');
    typingEl.className = 'text-left';
    typingEl.innerHTML = `
      <span class="inline-block px-3 py-2 rounded-lg bg-black/30 border border-white/10">
        <span class="inline-block w-2 h-2 bg-gray-400 rounded-full animate-pulse"></span>
        <span class="inline-block w-2 h-2 bg-gray-500 rounded-full animate-pulse ml-1"></span>
        <span class="inline-block w-2 h-2 bg-gray-600 rounded-full animate-pulse ml-1"></span>
      </span>`;
    chatBox.appendChild(typingEl);
    chatBox.scrollTop = chatBox.scrollHeight;
  }
  function hideTyping() {
    if (typingEl && typingEl.parentNode) typingEl.parentNode.removeChild(typingEl);
    typingEl = null;
  }

  // 気分をセット（UI反映＋保存）
  function setMood(mood) {
    currentMood = mood;
    localStorage.setItem('cc_mood', mood);

    // セッションが無ければ開始（1時間単位）
    if (isSessionExpired()) startNewSession();

    // ボタンを左側に小さく配置
    const panel = document.getElementById('mood-panel');
    panel.classList.add('small');
    // ボタン押下時は毎回挨拶を送る（小さくなった後でも反応させたい）
    console.log('setMood -> calling startCatGreeting', mood);
    startCatGreeting(mood);
  }

  // 初回あいさつ（自発話しかけ）
  async function startCatGreeting(mood) {
    console.log('startCatGreeting invoked, mood=', mood);
    sendBtn.disabled = true;
    sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
    showTyping();

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(API_CHAT, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json', 
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ message: "__start__", mood })
      });

      const raw = await res.text();
      let data = null; try { data = JSON.parse(raw); } catch {}

      hideTyping();

      if (res.ok && data && typeof data.reply === 'string') {
        console.log('startCatGreeting: reply received', data.reply);
        addBubble(data.reply, 'left');
      } else {
        addBubble('…最初の挨拶がうまく出せなかったにゃ。', 'left');
        console.error('startCatGreeting HTTP', res.status, 'Body:', raw);
      }
    } catch (err) {
      hideTyping();
      addBubble('…接続が不安定みたいにゃ。', 'left');
      console.error('startCatGreeting error', err);
    } finally {
      sendBtn.disabled = false;
      sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
    }
  }

  // ページ読み込み時：セッション管理(1時間)と前回の気分反映
  (function initMoodFromStorage() {
  const saved = localStorage.getItem('cc_mood');
  const sessionExpired = isSessionExpired();

  if (sessionExpired) {
    // 新しいセッションの開始 => 大きな中央パネルを見せる
    startNewSession();
    moodPanel.classList.remove('small');
    if (saved) currentMood = saved; // 保存はそのまま残すが見た目は大きくする
  } else {
    // セッション継続中
    if (saved) {
      currentMood = saved;
      moodPanel.classList.add('small');
    } else {
      // 気分未選択なら大きなパネルで選ばせる
      moodPanel.classList.remove('small');
    }
  }
})();

  // 気分ボタンにリスナを付与
  try {
    const moodButtons = document.querySelectorAll('[data-mood]');
    if (!moodButtons || moodButtons.length === 0) console.warn('No mood buttons found');
    moodButtons.forEach(btn => {
      btn.addEventListener('click', (ev) => {
        const mood = btn.getAttribute('data-mood'); // "bad" | "neutral" | "good"
        console.log('mood button clicked', mood, ev);
        setMood(mood);
      });
      // touch fallback
      btn.addEventListener('touchstart', (ev) => {
        const mood = btn.getAttribute('data-mood');
        console.log('mood button touchstart', mood);
        setMood(mood);
      }, { passive: true });
    });
  } catch (err) {
    console.error('Failed to attach mood button listeners', err);
  }

  // 送信（通常の会話）
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addBubble(text, 'right');
    input.value = '';
    input.focus();

    sendBtn.disabled = true;
    sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
    showTyping();

    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(API_CHAT, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json', 
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ message: text, mood: currentMood })   // ← 気分も送る
      });

      const raw = await res.text();
      let data = null; try { data = JSON.parse(raw); } catch {}

      hideTyping();

      if (res.ok && data && typeof data.reply === 'string') {
        addBubble(data.reply, 'left');
        if (data.error) console.warn('API error:', data.error, data.status);
      } else {
        addBubble('…サーバーから正しい返事が来なかったにゃ。', 'left');
        console.error('HTTP', res.status, 'Body:', raw);
      }
    } catch (err) {
      hideTyping();
      addBubble('…接続が不安定みたいにゃ。', 'left');
      console.error(err);
    } finally {
      sendBtn.disabled = false;
      sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
    }
  });
</script>


</body>
</html>
