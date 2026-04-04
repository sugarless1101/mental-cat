<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mental Cat — Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; }
        .stat-card { background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 1.25rem; }
        .stat-label { font-size: 0.75rem; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 2rem; font-weight: 700; color: #B6A7F2; margin-top: 0.25rem; }
        .stat-sub { font-size: 0.8rem; color: #6B7280; margin-top: 0.25rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 0.875rem; }
        th { color: #9CA3AF; font-weight: 500; }
        td { color: #D1D5DB; }
        .badge-ok { background: #065f46; color: #6ee7b7; padding: 1px 8px; border-radius: 9999px; font-size: 0.75rem; }
        .badge-fail { background: #7f1d1d; color: #fca5a5; padding: 1px 8px; border-radius: 9999px; font-size: 0.75rem; }
    </style>
</head>
<body class="bg-[#0A0A0A] text-gray-300 min-h-screen p-6">

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-white">🐈‍⬛ Mental Cat <span class="text-accent text-lg font-normal ml-2">Admin</span></h1>
            <span class="text-xs text-gray-500">{{ now()->timezone('Asia/Tokyo')->format('Y/m/d H:i') }} JST</span>
        </div>

        {{-- LLM使用状況（今月） --}}
        <section class="mb-8">
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-4">LLM 使用状況（今月）</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="stat-card">
                    <div class="stat-label">呼び出し回数</div>
                    <div class="stat-value">{{ number_format($llmStats->total_calls ?? 0) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">トークン合計</div>
                    <div class="stat-value">{{ number_format($llmStats->total_tokens ?? 0) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">推定コスト</div>
                    <div class="stat-value">${{ number_format($llmStats->total_cost ?? 0, 4) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">平均レスポンス</div>
                    <div class="stat-value">{{ number_format($llmStats->avg_latency ?? 0) }}<span class="text-base font-normal ml-1">ms</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">成功率</div>
                    <div class="stat-value">{{ number_format($llmStats->success_rate ?? 0, 1) }}<span class="text-base font-normal ml-1">%</span></div>
                </div>
            </div>
        </section>

        {{-- Guardrails --}}
        <section class="mb-8">
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-4">Guardrails（今月）</h2>
            <div class="grid grid-cols-2 gap-4 max-w-sm">
                <div class="stat-card">
                    <div class="stat-label">フォールバック回数</div>
                    <div class="stat-value text-red-400">{{ number_format($llmStats->fallback_count ?? 0) }}</div>
                    <div class="stat-sub">ok=false の件数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">全期間コスト</div>
                    <div class="stat-value">${{ number_format($llmAllTime->total_cost ?? 0, 4) }}</div>
                    <div class="stat-sub">全 {{ number_format($llmAllTime->total_calls ?? 0) }} 件</div>
                </div>
            </div>
        </section>

        {{-- ユーザー概況 --}}
        <section class="mb-8">
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-4">ユーザー概況</h2>
            <div class="grid grid-cols-3 gap-4 max-w-lg">
                <div class="stat-card">
                    <div class="stat-label">総ユーザー数</div>
                    <div class="stat-value">{{ number_format($totalUsers) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">今日のアクティブ</div>
                    <div class="stat-value">{{ number_format($todayActive) }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">気分ログ総数</div>
                    <div class="stat-value">{{ number_format($totalMoodLogs) }}</div>
                </div>
            </div>
        </section>

        {{-- 直近のLLMログ --}}
        <section>
            <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-widest mb-4">直近の LLM ログ（20件）</h2>
            <div class="stat-card overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>時刻 (JST)</th>
                            <th>モデル</th>
                            <th>tokens in</th>
                            <th>tokens out</th>
                            <th>latency</th>
                            <th>cost</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(\App\Models\LlmLog::latest()->limit(20)->get() as $log)
                        <tr>
                            <td>{{ $log->created_at->timezone('Asia/Tokyo')->format('m/d H:i:s') }}</td>
                            <td>{{ $log->model }}</td>
                            <td>{{ number_format($log->tokens_in) }}</td>
                            <td>{{ number_format($log->tokens_out) }}</td>
                            <td>{{ number_format($log->latency_ms) }}ms</td>
                            <td>${{ number_format($log->cost_estimate, 5) }}</td>
                            <td>
                                @if($log->ok)
                                    <span class="badge-ok">ok</span>
                                @else
                                    <span class="badge-fail">fail</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-gray-500 py-4 text-center">まだログがありません</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

</body>
</html>
