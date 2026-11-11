<?php

use App\Http\Middleware\CatatAktivitas;
use App\Http\Middleware\EnsureAccountValid;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Services\WorkflowException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureAccountValid::class, CatatAktivitas::class]);
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Di belakang reverse proxy/load balancer instansi: isi TRUSTED_PROXIES di .env
        // Isi "*" bila di belakang proxy yang IP-nya berubah-ubah (mis. Vercel).
        $proxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(at: $proxies === '*' ? '*' : ($proxies ? explode(',', $proxies) : null));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Pelanggaran aturan alur => kembali ke halaman sebelumnya dengan pesan, bukan error 500.
        $exceptions->render(function (WorkflowException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password']);

        // Di Vercel log dipotong per entri: catat juga ringkasan satu baris agar penyebab error terbaca.
        if (env('VERCEL')) {
            $exceptions->report(function (\Throwable $e) {
                $p = $e->getPrevious();
                error_log('SIPELAB-ERROR '.get_class($e).': '.mb_substr($e->getMessage(), 0, 300).' @ '.basename($e->getFile()).':'.$e->getLine()
                    .($p ? ' | sebab: '.get_class($p).': '.mb_substr($p->getMessage(), 0, 200) : ''));
            });
        }
    })->create();
