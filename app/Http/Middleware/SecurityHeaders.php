<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // PHP バージョン情報を除去
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        // クリックジャッキング防止
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // MIMEスニッフィング防止
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // リファラー情報制限
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // CSP は local 環境ではスキップ（Vite HMR が localhost:5173 を必要とするため）
        if (! app()->environment('local')) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
                "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:",
                'frame-src https://www.youtube.com',
                "img-src 'self' data: https:",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
