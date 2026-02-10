@extends('layouts.guest')

@section('content')
  <!-- header -->
  <header class="flex justify-between items-center p-4 bg-white/5 border-b border-white/10">
    <p class="text-xl tracking-wide">Calm Cat</p>
    <div class="relative">
      <button id="hamburger-btn" class="text-graylight hover:text-accent transition" aria-label="menu">
        <i class="fa-solid fa-bars"></i>
      </button>

      <!-- メニュー（トグル表示） -->
      <div id="hamburger-menu" class="hidden absolute right-0 mt-2 w-40 bg-white/5 border border-white/10 rounded-md shadow-md py-2 z-20">
        @guest
          <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">ログイン</a>
          <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">アカウント作成</a>
        @endguest

        @auth
          <a href="{{ route('app') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">管理ページ</a>
          <form id="logout-form-menu" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-graylight hover:bg-white/10">ログアウト</button>
          </form>
        @endauth
      </div>
    </div>
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
  const API_MOOD = "{{ url('/api/mood-log') }}";

  const chatBox   = document.getElementById('chat-box');
  const form      = document.getElementById('chat-form');
  const input     = document.getElementById('message');
  const sendBtn   = document.getElementById('send-btn');

  const moodPanel = document.getElementById('mood-panel');
  const moodChip  = document.getElementById('mood-chip');
  const moodChipText = document.getElementById('mood-chip-text');

  let currentMood = null;

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

    // ボタンを左側に小さく配置
    const panel = document.getElementById('mood-panel');
    panel.classList.add('small');

    // 猫が気分に応じて自発発言
    startCatGreeting(mood);

    // ログイン中であればサーバーに記録（失敗してもUIは止めない）
    try {
      if (window.IS_AUTHENTICATED) {
        const tokenEl = document.querySelector('meta[name="csrf-token"]');
        const token = tokenEl ? tokenEl.getAttribute('content') : '';
        fetch(API_MOOD, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
          body: JSON.stringify({ mood })
        }).catch(err => console.warn('mood save failed', err));
      }
    } catch (e) {
      console.warn('mood save error', e);
    }
  }

  // 初回あいさつ（自発話しかけ）
  async function startCatGreeting(mood) {
    sendBtn.disabled = true;
    sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
    showTyping();

    try {
      const res = await fetch(API_CHAT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ message: "__start__", mood })
      });

      const raw = await res.text();
      let data = null; try { data = JSON.parse(raw); } catch {}

      hideTyping();

      if (res.ok && data && typeof data.reply === 'string') {
        addBubble(data.reply, 'left');
      } else {
        addBubble('…最初の挨拶がうまく出せなかったにゃ。', 'left');
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
  }

  // ページ読み込み時：前回の気分があれば反映
  (function initMoodFromStorage() {
    const saved = localStorage.getItem('cc_mood');
    if (saved) {
      currentMood = saved;
      // 中央パネルを非表示にせず、小さく左に寄せるだけ
      moodPanel.classList.add('small');
    }
  })();

  // 気分ボタンにリスナを付与
  document.querySelectorAll('[data-mood]').forEach(btn => {
    btn.addEventListener('click', () => {
      const mood = btn.getAttribute('data-mood'); // "bad" | "neutral" | "good"
      setMood(mood);
    });
  });

  // ハンバーガーメニューの開閉（最小JS）
  (function setupHamburger(){
    const btn = document.getElementById('hamburger-btn');
    const menu = document.getElementById('hamburger-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('hidden');
    });

    // クリックがメニュー外なら閉じる
    document.addEventListener('click', (e) => {
      if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !btn.contains(e.target)) {
        menu.classList.add('hidden');
      }
    });

    // Esc キーで閉じる
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') menu.classList.add('hidden');
    });
  })();

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
      const res = await fetch(API_CHAT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
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
@endsection