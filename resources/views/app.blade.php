<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mental Cat - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* 軽量な猫アニメーション */
        .cat-float { animation: floaty 6s ease-in-out infinite; }
        @keyframes floaty { 0%{transform:translateY(0)}50%{transform:translateY(-6px)}100%{transform:translateY(0)} }
        .cat-blink { animation: blink 5s steps(1) infinite; }
        @keyframes blink { 0%,95%{opacity:1}98%{opacity:0}100%{opacity:1} }
        /* 背景ゆらぎ */
        .bg-wobble { background-image: radial-gradient(rgba(99,102,241,0.06) 0, transparent 40%); background-size: 300px 300px; animation: wobble 10s linear infinite; }
        @keyframes wobble { 0%{background-position:0 0}50%{background-position:40px 20px}100%{background-position:0 0} }
        .praise { position:fixed; right:24px; top:100px; background:#fef3c7; padding:8px 12px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body class="bg-gray-50 bg-wobble min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-6">
        <header class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">🐈‍⬛ Mental Cat</h1>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm">ログアウト</button></form>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <aside class="space-y-6">
                @include('partials.mood', ['todayMood' => $todayMood])
            </aside>

            <section class="lg:col-span-2 space-y-6">
                @include('partials.chat', ['chatMessages' => $chatMessages])
            </section>

            <aside class="space-y-6 lg:col-span-1">
                @include('partials.tasks', ['tasks' => $tasks, 'doneRecent' => $doneRecent])
                @include('partials.bgm', ['bgmKey' => $bgmKey])
            </aside>
        </div>
    </div>

    <div id="praise" class="praise hidden">えらいにゃ！了解にゃ！</div>

</body>
</html>
