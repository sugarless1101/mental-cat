<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">チャット</h2>

    <div id="chat-messages" class="space-y-4 max-h-96 overflow-y-auto mb-4">
        @foreach($chatMessages as $msg)
            <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-xs px-4 py-2 rounded-lg {{ $msg->role === 'user' ? 'bg-blue-100 text-blue-900' : 'bg-gray-100 text-gray-900' }}">
                    <p class="text-sm">{{ $msg->content }}</p>
                    <p class="text-xs mt-1 opacity-70">{{ $msg->created_at->format('H:i') }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex space-x-2">
        <input id="chat-input" type="text" class="flex-1 border rounded px-3 py-2" placeholder="猫に話しかける">
        <button id="chat-send" class="px-4 py-2 bg-indigo-600 text-white rounded">送信</button>
    </div>

    <div id="chat-error" class="text-red-500 text-sm mt-2 hidden">通信エラーが発生しました。</div>

    <script>
        (function(){
            const sendBtn = document.getElementById('chat-send');
            const input = document.getElementById('chat-input');
            const messagesEl = document.getElementById('chat-messages');
            const tasksContainer = document.getElementById('tasks-container');
            const bgmIframe = document.getElementById('bgm-iframe');
            const praiseEl = document.getElementById('praise');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function renderMessages(arr){
                messagesEl.innerHTML = '';
                arr.forEach(m=>{
                    const wrap = document.createElement('div');
                    wrap.className = (m.role === 'user') ? 'flex justify-end' : 'flex justify-start';
                    const box = document.createElement('div');
                    box.className = 'max-w-xs px-4 py-2 rounded-lg ' + (m.role === 'user' ? 'bg-blue-100 text-blue-900' : 'bg-gray-100 text-gray-900');
                    box.innerHTML = '<p class="text-sm">'+m.content+'</p>';
                    wrap.appendChild(box);
                    messagesEl.appendChild(wrap);
                });
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            function renderTasks(tasks){
                if(!tasksContainer) return;
                tasksContainer.querySelector('#todo-list').innerHTML = tasks.todo.map(t => `<li class="flex items-start"><input data-id="${t.id}" type="checkbox" class="mt-1 mr-3 task-checkbox"> <span class="text-gray-700">${t.title}</span></li>`).join('');
                tasksContainer.querySelector('#done-list').innerHTML = tasks.done_recent.map(t => `<li class="text-sm text-gray-500">${t.title}</li>`).join('');
            }

            async function sendMessage(){
                const text = input.value.trim();
                if(!text) return;
                sendBtn.disabled = true;
                document.getElementById('chat-error').classList.add('hidden');
                try{
                    const res = await fetch('{{ route('app.chat.store') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ message: text })
                    });
                    const json = await res.json();
                    if(json.ok){
                        renderMessages(json.messages || [{role:'assistant', content: json.reply}]);
                        renderTasks(json.tasks || { todo: [], done_recent: [] });
                        if(json.bgm_key && bgmIframe){
                            const map = JSON.parse('{!! json_encode(config('bgm')) !!}');
                            const id = map[json.bgm_key] || map['calm'];
                            bgmIframe.src = 'https://www.youtube.com/embed/' + id + '?autoplay=1&mute=1';
                        }
                        input.value = '';
                    } else {
                        document.getElementById('chat-error').classList.remove('hidden');
                    }
                } catch (err){
                    document.getElementById('chat-error').classList.remove('hidden');
                } finally { sendBtn.disabled = false; }
            }

            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keydown', (e)=>{ if(e.key==='Enter') sendMessage(); });

            // Delegate task checkbox clicks
            document.addEventListener('click', async (e)=>{
                if(e.target && e.target.classList.contains('task-checkbox')){
                    const id = e.target.getAttribute('data-id');
                    if(!confirm('手動で完了にしますか？')){ e.target.checked = false; return; }
                    try{
                        const res = await fetch('/app/tasks/'+id+'/complete', { method:'POST', headers:{'X-CSRF-TOKEN': token} });
                        const j = await res.json();
                        if(j.ok){
                            // show praise briefly
                            const p = document.getElementById('praise'); p.classList.remove('hidden');
                            setTimeout(()=>p.classList.add('hidden'), 1500);
                            // refresh by simulating a chat reload: fetch current tasks via a light call
                            // for simplicity, reload page section by requesting /app and replacing tasks container
                            const html = await fetch(location.pathname).then(r=>r.text());
                            const tmp = document.createElement('div'); tmp.innerHTML = html;
                            const newTasks = tmp.querySelector('#tasks-container');
                            if(newTasks && tasksContainer) tasksContainer.innerHTML = newTasks.innerHTML;
                        } else { alert('操作に失敗しました'); }
                    } catch (err){ alert('通信エラー'); }
                }
            });
        })();
    </script>
</div>
