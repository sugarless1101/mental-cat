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
    #mood-panel.small .cat-caption { display: none; }
    #mood-panel { z-index: 60; pointer-events: auto; }
    #mood-panel button { cursor: pointer; padding: .4rem .6rem; border-radius: .5rem; pointer-events: auto; }
    #mood-panel.small ul li button { padding: .5rem .75rem; }
    .task_list { z-index: 10; }
  </style>
</head>

<body class="bg-dark text-graylight min-h-screen flex flex-col justify-between font-sans">
  <header class="flex justify-between items-center p-4 bg-white/5 border-b border-white/10">
    <p class="text-xl tracking-wide">Mental Cat</p>
    <button class="text-graylight hover:text-accent transition" aria-label="menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </header>

  <main class="flex flex-1 justify-center items-center relative p-4">
    <div class="task_list absolute left-8 top-1/2 -translate-y-1/2 w-1/5 bg-white/5 rounded-xl p-4 text-sm text-gray-300 shadow-md border border-white/10">
      <p class="text-accent mb-3 font-semibold tracking-widest">TASK</p>
      <ul id="task-items" class="space-y-3">
        <li class="flex justify-between items-center">
          <span>気分を選ぶとおすすめタスクが表示されます</span>
          <input type="checkbox" disabled class="w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded opacity-60">
        </li>
      </ul>
    </div>

    <div id="mood-panel" class="mental_button flex flex-col items-center space-y-8 transition-all duration-700">
      <ul id="mood-buttons" class="flex space-x-6 text-4xl">
        <li><button data-mood="bad" class="hover:scale-110 transition" aria-label="bad">😞</button></li>
        <li><button data-mood="neutral" class="hover:scale-110 transition" aria-label="neutral">🙂</button></li>
        <li><button data-mood="good" class="hover:scale-110 transition" aria-label="good">😊</button></li>
      </ul>
      <div class="mt-12 text-center">
        <p class="cat-caption text-gray-400 text-lg italic animate-pulse">
          きょうの気分に合わせて、猫が提案するよ。
        </p>
      </div>
    </div>

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

  <div class="chat fixed bottom-4 left-1/2 -translate-x-1/2 w-3/4 max-w-2xl bg-white/5 rounded-xl p-3 border border-white/10 backdrop-blur-md">
    <div id="chat-box" class="h-56 overflow-y-auto mb-3 space-y-2 pr-1"></div>

    <form id="chat-form" class="flex items-center gap-3">
      <input
        id="message"
        type="text"
        placeholder="猫に話しかけてみて"
        class="flex-1 bg-transparent border-b border-gray-600 text-gray-200 focus:outline-none focus:border-accent p-2 placeholder-gray-500">
      <button
        id="send-btn"
        type="submit"
        class="bg-accent text-dark rounded-full w-10 h-10 flex items-center justify-center hover:bg-graylight transition"
        aria-label="send">
        ▶
      </button>
    </form>
  </div>

  <script>
  const API_CHAT = "{{ url('/api/chat') }}";
  const chatBox = document.getElementById('chat-box');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('message');
  const sendBtn = document.getElementById('send-btn');
  const moodPanel = document.getElementById('mood-panel');
  const taskItems = document.getElementById('task-items');
  let currentMood = null;

  function addBubble(text, side = 'left') {
    const wrap = document.createElement('div');
    wrap.className = side === 'right' ? 'text-right' : 'text-left';
    const bubble = document.createElement('span');
    bubble.className = 'inline-block max-w-[85%] px-3 py-2 rounded-lg whitespace-pre-line ' +
      (side === 'right' ? 'bg-white/20 border border-white/20' : 'bg-black/30 border border-white/10');
    bubble.textContent = text;
    wrap.appendChild(bubble);
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
  }

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

  function sanitizeTaskTitle(value) {
    if (typeof value !== 'string') return '';
    return value.replace(/\s+/g, ' ').trim();
  }

  function pickTopTodoTasks(data) {
    const todo = (data && data.tasks && Array.isArray(data.tasks.todo)) ? data.tasks.todo : [];
    return todo.map(t => sanitizeTaskTitle(t && t.title)).filter(Boolean).slice(0, 3);
  }

  function renderTaskPanel(taskTitles) {
    if (!taskTitles.length) return;
    taskItems.innerHTML = '';
    taskTitles.forEach((title) => {
      const li = document.createElement('li');
      li.className = 'flex justify-between items-center';

      const span = document.createElement('span');
      span.textContent = title;

      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.className = 'w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded';

      li.appendChild(span);
      li.appendChild(checkbox);
      taskItems.appendChild(li);
    });
  }

  function renderTaskRecommendationBubble(taskTitles) {
    if (!taskTitles.length) return;
    const lines = taskTitles.map((title, index) => `${index + 1}. ${title}`);
    addBubble(`今日のおすすめタスクだよ\n${lines.join('\n')}`, 'left');
  }

  function applyTaskSuggestionFromPayload(data, showRecommendation) {
    const topTasks = pickTopTodoTasks(data);
    if (!topTasks.length) return;
    renderTaskPanel(topTasks);
    if (showRecommendation) renderTaskRecommendationBubble(topTasks);
  }

  function setMood(mood) {
    currentMood = mood;
    localStorage.setItem('cc_mood', mood);
    moodPanel.classList.add('small');
    startCatGreeting(mood);
  }

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
        applyTaskSuggestionFromPayload(data, true);
      } else {
        addBubble('最初の挨拶がうまく出せなかったにゃ。', 'left');
      }
    } catch (err) {
      hideTyping();
      addBubble('接続が不安定みたいにゃ。', 'left');
    } finally {
      sendBtn.disabled = false;
      sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
    }
  }

  (function initMoodFromStorage() {
    const saved = localStorage.getItem('cc_mood');
    if (saved) {
      currentMood = saved;
      moodPanel.classList.add('small');
    }
  })();

  document.querySelectorAll('[data-mood]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const mood = btn.getAttribute('data-mood');
      setMood(mood);
    });
  });

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
        body: JSON.stringify({ message: text, mood: currentMood })
      });

      const raw = await res.text();
      let data = null; try { data = JSON.parse(raw); } catch {}

      hideTyping();
      if (res.ok && data && typeof data.reply === 'string') {
        addBubble(data.reply, 'left');
        applyTaskSuggestionFromPayload(data, false);
      } else {
        addBubble('サーバーから正しい返事が来なかったにゃ。', 'left');
      }
    } catch (err) {
      hideTyping();
      addBubble('接続が不安定みたいにゃ。', 'left');
    } finally {
      sendBtn.disabled = false;
      sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
    }
  });
  </script>
</body>
</html>
