<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mental Cat - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
    <style>
        .cat-float { animation: floaty 6s ease-in-out infinite; }
        @keyframes floaty { 0%{transform:translateY(0)}50%{transform:translateY(-6px)}100%{transform:translateY(0)} }
        .cat-blink { animation: blink 5s steps(1) infinite; }
        @keyframes blink { 0%,95%{opacity:1}98%{opacity:0}100%{opacity:1} }
        .praise { position:fixed; right:24px; top:100px; background:#fef3c7; color:#111827; padding:8px 12px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.25); }
    </style>
</head>
<body class="bg-[#0A0A0A] text-gray-300 min-h-screen">
  @yield('content')
</body>
</html>
