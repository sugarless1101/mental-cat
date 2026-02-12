@extends('layouts.guest')

@section('content')
<style>
  .bg-circle-layer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    top: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
  }
  .bg-circle {
    animation: grow-circle 3s linear;
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 50%;
    position: absolute;
    height: 50px;
    width: 50px;
  }
  @keyframes grow-circle {
    to {
      opacity: 0;
      transform: scale(5);
    }
  }
  #mood-panel {
    z-index: 60;
    pointer-events: auto;
  }
  #mood-panel button {
    cursor: pointer;
    padding: 0.4rem 0.6rem;
    border-radius: 0.5rem;
    pointer-events: auto;
  }
  #mood-panel.small ul li button {
    padding: 0.5rem 0.75rem;
  }
  .task_list {
    z-index: 10;
  }
</style>

<div id="bg-circle-layer" class="bg-circle-layer" aria-hidden="true"></div>

<header id="app-header" class="relative z-20 flex justify-between items-center p-4 bg-[#222222] border-b border-white/10">
  <p class="text-xl tracking-wide">Mental Cat</p>
  <div class="relative z-50">
    <button id="hamburger-btn" class="text-graylight hover:text-accent transition" aria-label="menu">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div id="hamburger-menu" class="hidden absolute right-0 mt-2 w-40 bg-[#222222] border border-white/10 rounded-md shadow-md py-2 z-[120] pointer-events-auto">
      @guest
        <a href="{{ route('login') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">ログイン</a>
        <a href="{{ route('register') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">アカウント作成</a>
      @endguest
      @auth
        <a href="{{ route('app') }}" class="block px-4 py-2 text-sm text-graylight hover:bg-white/10">ダッシュボード</a>
        <form id="logout-form-menu" method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="w-full text-left px-4 py-2 text-sm text-graylight hover:bg-white/10">ログアウト</button>
        </form>
      @endauth
    </div>
  </div>
</header>

<main class="relative z-10 flex flex-1 justify-center items-center p-4">
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
      <p class="cat-caption text-gray-400 text-lg italic animate-pulse">きょうの気分に合わせて、猫が提案するよ。</p>
    </div>
  </div>

  <div class="recommend_list absolute right-8 top-1/2 -translate-y-1/2 w-1/4 text-center bg-white/5 rounded-xl p-4 shadow-md border border-white/10">
    <ul class="space-y-4">
      <li>
        <iframe class="rounded-lg shadow-sm" width="100%" height="200"
          src="https://www.youtube.com/embed/nJktCw68UDo?si=FxJvdYa9KU6KePID"
          title="YouTube video player" frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
      </li>
      <li class="flex justify-center space-x-6 text-xl">
        <a href="https://x.com" target="_blank" aria-label="X (Twitter)" class="hover:text-accent transition"><i class="fa-brands fa-x-twitter"></i></a>
        <a href="https://www.instagram.com" target="_blank" aria-label="Instagram" class="hover:text-accent transition"><i class="fa-brands fa-instagram"></i></a>
      </li>
    </ul>
  </div>
</main>

<div class="chat fixed z-10 bottom-4 left-1/2 -translate-x-1/2 w-3/4 max-w-2xl bg-white/5 rounded-xl p-3 border border-white/10 backdrop-blur-md">
  <div id="chat-box" class="h-56 overflow-y-auto mb-3 space-y-2 pr-1"></div>
  <form id="chat-form" class="flex items-center gap-3">
    <input id="message" type="text" placeholder="猫に話しかけてみて"
      class="flex-1 bg-transparent border-b border-gray-600 text-gray-200 focus:outline-none focus:border-accent p-2 placeholder-gray-500">
    <button id="send-btn" type="submit" class="bg-accent text-dark rounded-full w-10 h-10 flex items-center justify-center hover:bg-graylight transition" aria-label="send">▶</button>
  </form>
</div>

<script>
const API_CHAT = "{{ url('/api/chat') }}";
const API_MOOD = "{{ url('/api/mood-log') }}";
const API_TASK_BASE = "{{ url('/app/tasks') }}";
const IS_AUTH = Boolean(window.IS_AUTHENTICATED);

const chatBox = document.getElementById('chat-box');
const form = document.getElementById('chat-form');
const input = document.getElementById('message');
const sendBtn = document.getElementById('send-btn');
const moodPanel = document.getElementById('mood-panel');
const taskItems = document.getElementById('task-items');

let currentMood = null;
let taskState = [];
let pendingTaskConfirm = null;

function addBubble(text, side = 'left') {
  const wrap = document.createElement('div');
  wrap.className = side === 'right' ? 'text-right' : 'text-left';
  const bubble = document.createElement('span');
  bubble.className = 'inline-block max-w-[85%] px-3 py-2 rounded-lg whitespace-pre-line ' + (side === 'right' ? 'bg-white/20 border border-white/20' : 'bg-black/30 border border-white/10');
  bubble.textContent = text;
  wrap.appendChild(bubble);
  chatBox.appendChild(wrap);
  chatBox.scrollTop = chatBox.scrollHeight;
}

let typingEl = null;
function showTyping() {
  typingEl = document.createElement('div');
  typingEl.className = 'text-left';
  typingEl.innerHTML = '<span class="inline-block px-3 py-2 rounded-lg bg-black/30 border border-white/10"><span class="inline-block w-2 h-2 bg-gray-400 rounded-full animate-pulse"></span><span class="inline-block w-2 h-2 bg-gray-500 rounded-full animate-pulse ml-1"></span><span class="inline-block w-2 h-2 bg-gray-600 rounded-full animate-pulse ml-1"></span></span>';
  chatBox.appendChild(typingEl);
  chatBox.scrollTop = chatBox.scrollHeight;
}
function hideTyping() {
  if (typingEl?.parentNode) typingEl.parentNode.removeChild(typingEl);
  typingEl = null;
}

function sanitizeTaskTitle(value) {
  if (typeof value !== 'string') return '';
  return value.replace(/\s+/g, ' ').trim();
}
function normalizeForMatch(value) {
  return sanitizeTaskTitle(value).toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
}
function taskStem(value) {
  return normalizeForMatch(value).replace(/(を)?(する|します|した|したよ|したよね|できた|完了|る|た|だ|んだ|んだよ)$/u, '');
}
function diceCoefficient(a, b) {
  if (!a || !b) return 0;
  if (a === b) return 1;
  if (a.length < 2 || b.length < 2) return 0;
  const grams = new Map();
  for (let i = 0; i < a.length - 1; i += 1) {
    const g = a.slice(i, i + 2);
    grams.set(g, (grams.get(g) || 0) + 1);
  }
  let overlap = 0;
  for (let i = 0; i < b.length - 1; i += 1) {
    const g = b.slice(i, i + 2);
    const count = grams.get(g) || 0;
    if (count > 0) {
      grams.set(g, count - 1);
      overlap += 1;
    }
  }
  return (2 * overlap) / ((a.length - 1) + (b.length - 1));
}

function parseTaskState(payload) {
  return (payload?.tasks?.todo ?? []).slice(0, 10).map((task) => ({
    id: task?.id ?? null,
    title: sanitizeTaskTitle(task?.title),
    status: task?.status === 'done' ? 'done' : 'todo',
  })).filter((t) => t.title);
}

function renderTaskPanel() {
  if (!taskState.length) {
    taskItems.innerHTML = '<li class="flex justify-between items-center"><span>気分を選ぶとおすすめタスクが表示されます</span><input type="checkbox" disabled class="w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded opacity-60"></li>';
    return;
  }
  taskItems.innerHTML = '';
  taskState.forEach((task) => {
    const li = document.createElement('li');
    li.className = 'flex justify-between items-center';
    const span = document.createElement('span');
    span.textContent = task.title;
    if (task.status === 'done') span.classList.add('line-through', 'opacity-70');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'w-4 h-4 accent-accent bg-transparent border border-gray-500 rounded';
    checkbox.checked = task.status === 'done';
    checkbox.disabled = true;
    li.appendChild(span);
    li.appendChild(checkbox);
    taskItems.appendChild(li);
  });
}

function mergeTaskStateFromPayload(payload, showRecommendation) {
  const incoming = parseTaskState(payload);
  if (!incoming.length) return;
  const existingDone = taskState.filter((t) => t.status === 'done');
  const merged = incoming.map((task) => {
    const matchedDone = existingDone.find((done) => {
      const sameId = done.id && task.id && String(done.id) === String(task.id);
      const sameTitle = done.title === task.title;
      return sameId || sameTitle;
    });
    return matchedDone ? { ...task, status: 'done' } : task;
  });
  existingDone.forEach((done) => {
    const exists = merged.find((t) => (done.id && t.id && String(done.id) === String(t.id)) || t.title === done.title);
    if (!exists) merged.push(done);
  });
  taskState = merged;
  renderTaskPanel();
  if (showRecommendation) {
    const lines = incoming.slice(0, 3).map((t, i) => `${i + 1}. ${t.title}`);
    if (lines.length) addBubble(`今日のおすすめタスクだよ\n${lines.join('\n')}`, 'left');
  }
}

function containsCompletionIntent(text) {
  return /(終わった|終えた|完了|できた|やった|済んだ|達成|したよ|した|しました|してきた|してきました|たよ|ました|done|finished)/i.test(text);
}
function findCompletionCandidate(text) {
  if (!containsCompletionIntent(text)) return null;
  const message = normalizeForMatch(text);
  let best = null;
  taskState.filter((t) => t.status !== 'done').forEach((task) => {
    const full = normalizeForMatch(task.title);
    const stem = taskStem(task.title);
    let score = 0;
    if (full && message.includes(full)) score += 10;
    if (stem && stem.length >= 4 && message.includes(stem)) score += 8;
    score += diceCoefficient(message, full) * 6;
    score += diceCoefficient(message, stem) * 4;
    if (score > 0 && (!best || score > best.score)) best = { ...task, score };
  });
  if (!best || best.score < 4.5) return null;
  return best;
}
function isAffirmative(text) {
  return /^(はい|うん|yes|ok|了解|お願い|そう)/i.test(text.trim());
}
function isNegative(text) {
  return /^(いいえ|いや|no|違う|まだ|キャンセル)/i.test(text.trim());
}

async function persistTaskDone(task) {
  if (!task?.id || !IS_AUTH) return true;
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try {
    const res = await fetch(`${API_TASK_BASE}/${task.id}/complete`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
    });
    const json = await res.json();
    return Boolean(json.ok);
  } catch {
    return false;
  }
}

async function handlePendingTaskConfirmation(messageText) {
  if (!pendingTaskConfirm) return false;
  const target = pendingTaskConfirm;
  if (isAffirmative(messageText)) {
    taskState = taskState.map((task) => {
      const sameId = target.id && task.id && String(target.id) === String(task.id);
      const sameTitle = task.title === target.title;
      return (sameId || sameTitle) ? { ...task, status: 'done' } : task;
    });
    renderTaskPanel();
    await persistTaskDone(target);
    addBubble(`いいね、達成できたにゃ。「${target.title}」すごいにゃ。`, 'left');
    pendingTaskConfirm = null;
    return true;
  }
  if (isNegative(messageText)) {
    addBubble('了解にゃ。今回は未完了のままにしておくにゃ。', 'left');
    pendingTaskConfirm = null;
    return true;
  }
  addBubble(`「${target.title}」を達成したにゃんね？（はい/いいえ）`, 'left');
  return true;
}

async function sendChatMessage(message, mood, showRecommendation = false) {
  sendBtn.disabled = true;
  sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
  showTyping();
  try {
    const res = await fetch(API_CHAT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ message, mood }),
    });
    const raw = await res.text();
    let data = null;
    try { data = JSON.parse(raw); } catch {}
    hideTyping();
    if (res.ok && data && typeof data.reply === 'string') {
      addBubble(data.reply, 'left');
      if (showRecommendation) {
        mergeTaskStateFromPayload(data, true);
      }
    } else {
      addBubble('サーバーから正しい返事が来なかったにゃ。', 'left');
    }
  } catch {
    hideTyping();
    addBubble('接続が不安定みたいにゃ。', 'left');
  } finally {
    sendBtn.disabled = false;
    sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
  }
}

function setMood(mood) {
  currentMood = mood;
  localStorage.setItem('cc_mood', mood);
  moodPanel.classList.add('small');
  sendChatMessage('__start__', mood, true);
  if (IS_AUTH) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch(API_MOOD, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: JSON.stringify({ mood }),
    }).catch(() => {});
  }
}

(function init() {
  const saved = localStorage.getItem('cc_mood');
  if (saved) {
    currentMood = saved;
    moodPanel.classList.add('small');
  }
  renderTaskPanel();
  document.querySelectorAll('[data-mood]').forEach((btn) => {
    btn.addEventListener('click', () => setMood(btn.getAttribute('data-mood')));
  });

  const menuBtn = document.getElementById('hamburger-btn');
  const menu = document.getElementById('hamburger-menu');
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('hidden');
    });
    document.addEventListener('click', (e) => {
      if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !menuBtn.contains(e.target)) {
        menu.classList.add('hidden');
      }
    });
  }
})();

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  addBubble(text, 'right');
  input.value = '';
  input.focus();

  if (await handlePendingTaskConfirmation(text)) return;

  const candidate = findCompletionCandidate(text);
  if (candidate) {
    pendingTaskConfirm = candidate;
    addBubble(`「${candidate.title}」を達成したにゃんね？（はい/いいえ）`, 'left');
    return;
  }

  await sendChatMessage(text, currentMood, false);
});

function createCircle() {
  const layer = document.getElementById('bg-circle-layer');
  if (!layer) return;
  const circle = document.createElement('div');
  circle.classList.add('bg-circle');
  circle.style.top = `${Math.random() * layer.clientHeight}px`;
  circle.style.left = `${Math.random() * layer.clientWidth}px`;
  layer.appendChild(circle);
  setTimeout(() => circle.remove(), 3000);
}

function syncBgLayerTop() {
  const header = document.getElementById('app-header');
  const layer = document.getElementById('bg-circle-layer');
  if (!header || !layer) return;
  layer.style.top = `${header.offsetHeight}px`;
}

const bgCircleInterval = setInterval(createCircle, 300);
syncBgLayerTop();
window.addEventListener('resize', syncBgLayerTop);
window.addEventListener('beforeunload', () => clearInterval(bgCircleInterval));
</script>
@endsection
