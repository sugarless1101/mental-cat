<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">今日の気分</h2>

    <div class="flex items-center.justify-center space-x-4 mb-4">
        <button data-mood="good" class="mood-btn px-4 py-2 bg-green-100 rounded">😊 Good</button>
        <button data-mood="neutral" class="mood-btn px-4 py-2 bg-yellow-100 rounded">🙂 Neutral</button>
        <button data-mood="bad" class="mood-btn px-4 py-2 bg-red-100 rounded">😞 Bad</button>
    </div>

    <div id="today-mood-display" class="text-center text-gray-600">
        @if($todayMood)
            今日の気分：<strong>{{ $todayMood->mood }}</strong>
        @else
            まだ今日の気分を記録していません
        @endif
    </div>

    <script>
        (function(){
            const buttons = document.querySelectorAll('.mood-btn');
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            buttons.forEach(b=>b.addEventListener('click', async (e)=>{
                const mood = b.getAttribute('data-mood');
                b.disabled = true;
                try{
                    const res = await fetch('{{ route('app.mood.store') }}', {
                        method: 'POST',
                        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': token},
                        body: JSON.stringify({mood})
                    });
                    const json = await res.json();
                    if(json.ok){
                        document.getElementById('today-mood-display').textContent = '今日の気分：' + json.mood;
                    } else {
                        alert('気分の保存に失敗しました');
                    }
                } catch (err) {
                    alert('通信エラー');
                } finally { b.disabled = false; }
            }));
        })();
    </script>
</div>
