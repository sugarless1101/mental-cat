@extends('layouts.app')

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-6">
        <header class="flex items-center justify-between mb-6 p-4 rounded-xl bg-[#222222] border border-white/10">
            <h1 class="text-3xl font-bold">🐈‍⬛ Mental Cat</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="text-sm px-3 py-1.5 rounded-md border border-white/20 bg-white/5 hover:bg-white/10 transition">メインページへ戻る</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm">ログアウト</button>
                </form>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <aside class="space-y-6">
                {{-- Mood summary (read-only) --}}
                <div class="bg-white/5 border border-white/10 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">今日の気分</h2>
                    @if ($todayMood)
                        <div class="text-center">
                            <div class="text-4xl mb-2">
                                @switch($todayMood->mood)
                                    @case('good')
                                        😊
                                        @break
                                    @case('neutral')
                                        🙂
                                        @break
                                    @case('bad')
                                        😞
                                        @break
                                @endswitch
                            </div>
                            <p class="text-gray-400">
                                {{ $todayMood->logged_at->timezone('Asia/Tokyo')->format('H:i') }} に記録
                            </p>
                        </div>
                    @else
                        <p class="text-gray-400 text-center">まだ今日の気分を記録していません</p>
                    @endif
                </div>
            </aside>

            <section class="lg:col-span-2 space-y-6">
                {{-- Chat history (read-only) --}}
                <div class="mt-0 bg-white/5 border border-white/10 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">最近の会話</h2>
                    @if ($chatMessages->count() > 0)
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            @foreach ($chatMessages as $msg)
                                <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-xs px-4 py-2 rounded-lg {{ $msg->role === 'user' ? 'bg-white/15 border border-white/10 text-gray-200' : 'bg-black/30 border border-white/10 text-gray-200' }}">
                                        <p class="text-sm">{{ $msg->content }}</p>
                                        <p class="text-xs mt-1 opacity-70">{{ $msg->created_at->timezone('Asia/Tokyo')->format('H:i') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-400">まだ会話がありません。</p>
                    @endif
                </div>

                {{-- Mood logs list (global latest) --}}
                <div class="mt-6 bg-white/5 border border-white/10 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">最近の気分ログ（最新順）</h2>
                    @if(isset($moodLogs) && $moodLogs->count() > 0)
                        <div class="space-y-2 max-h-80 overflow-y-auto text-sm text-gray-300">
                            @foreach($moodLogs as $log)
                                <div class="flex items-center justify-between border-b border-white/10 py-2">
                                    <div>
                                        <div class="font-medium">@if($log->user) {{ $log->user->name }} @else 匿名 @endif</div>
                                        <div class="text-xs text-gray-400">{{ $log->logged_at->timezone('Asia/Tokyo')->format('Y/m/d H:i') }}</div>
                                    </div>
                                    <div class="text-lg font-semibold">
                                        @switch($log->mood)
                                            @case('good') 😊 @break
                                            @case('neutral') 🙂 @break
                                            @case('bad') 😞 @break
                                        @endswitch
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-400">まだ気分ログがありません。</p>
                    @endif
                </div>
            </section>

            <aside class="space-y-6 lg:col-span-1">
                {{-- Tasks read-only --}}
                <div class="bg-white/5 border border-white/10 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">To-Do</h2>
                    @if ($tasks->count() > 0)
                        <ul class="space-y-2">
                            @foreach ($tasks as $task)
                                <li class="flex items-start"><span class="text-gray-300">{{ $task->title }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-gray-400">やることがありません。</p>
                    @endif
                </div>

                <div class="bg-white/5 border border-white/10 rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold mb-4">最近完了</h2>
                    <ul class="space-y-1 text-sm text-gray-400">
                        @foreach($doneRecent as $d)
                            <li>{{ $d->title }}</li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </div>
    </div>
    <div id="praise" class="praise hidden">えらいにゃ！了解にゃ！</div>
@endsection
