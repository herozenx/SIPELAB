<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons. Tidak ada script/style inline di aplikasi,
 * sehingga CSP bisa ketat: hanya sumber dari domain sendiri.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'same-origin');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->remove('X-Powered-By');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Halaman berisi data pribadi tidak boleh disimpan di cache browser/proxy.
        if ($request->user()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
