<?php

namespace App\Providers;

use App\Models\Permohonan;
use App\Policies\PermohonanPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Workflow::class);
    }

    public function boot(): void
    {
        // Tangkap bug lebih awal (selain production): atribut tak terdaftar         // Tangkap bug lebih awal: atribut tak terdaftar, lazy loading N+1 (selain production). mass assignment diam-diam.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::preventLazyLoading(false);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::policy(Permohonan::class, PermohonanPolicy::class);
        Paginator::defaultView('partials.pagination');

        Password::defaults(fn () => Password::min(8)->max(128)->mixedCase()->numbers());

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('daftar', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('verifikasi', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('aksi', fn (Request $r) => Limit::perMinute(60)->by((string) ($r->user()?->id ?? $r->ip())));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(30)->by((string) ($r->user()?->id ?? $r->ip())));
        // Chat: polling pesan tiap 3 detik + status tiap 15 detik, beberapa tab sekaligus.
        RateLimiter::for('chat', fn (Request $r) => Limit::perMinute(150)->by('chat:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('chat-kirim', fn (Request $r) => Limit::perMinute(30)->by('kirim:'.($r->user()?->id ?? $r->ip())));
    }
}
