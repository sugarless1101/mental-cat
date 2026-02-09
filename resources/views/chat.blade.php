<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with Cat</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-gray-300 min-h-screen flex flex-col items-center justify-center">
  <div class="w-full max-w-md bg-white/10 p-6 rounded-xl shadow-lg">
    <h1 class="text-center text-xl mb-4">🐈‍⬛ 黒猫クロと話す</h1>

    <div id="chat-box" class="h-64 overflow-y-auto border border-gray-700 p-2 mb-4 rounded"></div>

    <form id="chat-form" class="flex space-x-2">
      <input id="message" type="text" placeholder="話しかけてみる…" class="flex-1 bg-black border border-gray-600 text-gray-100 p-2 rounded">
      <button class="bg-gray-200 text-black px-4 rounded hover:bg-white transition">送信</button>
    </form>
  </div>

  <script>
    const form = document.getElementById('chat-form');
    const chatBox = document.getElementById('chat-box');
    const input = document.getElementById('message');

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const userMessage = input.value.trim();
      if (!userMessage) return;

      chatBox.innerHTML += `<div class='text-right mb-2'><span class='bg-gray-700 px-3 py-1 rounded-lg inline-block'>${userMessage}</span></div>`;
      input.value = '';

      const res = await fetch('/api/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ message: userMessage }),
      });

      const data = await res.json();
      const reply = data.reply;

      chatBox.innerHTML += `<div class='text-left mb-2'><span class='bg-gray-800 px-3 py-1 rounded-lg inline-block'>${reply}</span></div>`;
      chatBox.scrollTop = chatBox.scrollHeight;
    });
  </script>
</body>
</html>
