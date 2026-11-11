<?php

use App\Http\Controllers\Admin\LabController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TimController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PermohonanController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

// Publik
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/verifikasi/{kode?}', [PublicController::class, 'verifikasi'])
    ->middleware('throttle:verifikasi')->where('kode', '[A-Za-z0-9]{1,20}')->name('verifikasi');
Route::get('/panduan', [PublicController::class, 'panduan'])->name('panduan');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:daftar');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');
    Route::get('/akun', [AuthController::class, 'akun'])->name('akun');
    Route::put('/akun/password', [AuthController::class, 'gantiPassword'])->middleware('throttle:aksi')->name('akun.password');

    Route::get('/beranda', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/versi', [PermohonanController::class, 'versi'])->middleware('throttle:api')->name('api.versi');

    // Permohonan
    Route::get('/permohonan', [PermohonanController::class, 'index'])
        ->middleware('role:pengguna_jasa,admin_lab,superadmin')->name('permohonan.index');
    Route::get('/permohonan/baru', [PermohonanController::class, 'create'])->middleware('role:pengguna_jasa')->name('permohonan.create');
    Route::post('/permohonan', [PermohonanController::class, 'store'])->middleware(['role:pengguna_jasa', 'throttle:aksi'])->name('permohonan.store');
    Route::get('/permohonan/{permohonan}', [PermohonanController::class, 'show'])->whereNumber('permohonan')->name('permohonan.show');
    Route::get('/permohonan/{permohonan}/dokumen/{dokumen}', [PermohonanController::class, 'dokumen'])
        ->whereNumber(['permohonan', 'dokumen'])->name('permohonan.dokumen');
    Route::get('/permohonan/{permohonan}/lhu', [PermohonanController::class, 'lhu'])->whereNumber('permohonan')->name('permohonan.lhu');

    Route::middleware('throttle:aksi')->whereNumber('permohonan')->group(function () {
        Route::post('/permohonan/{permohonan}/bayar', [PermohonanController::class, 'bayar'])->middleware('role:pengguna_jasa')->name('permohonan.bayar');
        Route::post('/permohonan/{permohonan}/hasil', [PermohonanController::class, 'inputHasil'])->middleware('role:petugas_lab')->name('permohonan.hasil');
        Route::post('/permohonan/{permohonan}/tangani', [PermohonanController::class, 'tangani'])->middleware('role:petugas_lab')->name('permohonan.tangani');
        Route::middleware('role:admin_lab,superadmin')->group(function () {
            Route::post('/permohonan/{permohonan}/verifikasi', [PermohonanController::class, 'verifikasi'])->name('permohonan.verifikasi');
            Route::post('/permohonan/{permohonan}/konfirmasi-bayar', [PermohonanController::class, 'konfirmasiBayar'])->name('permohonan.konfirmasi');
            Route::post('/permohonan/{permohonan}/validasi-lhu', [PermohonanController::class, 'validasiLhu'])->name('permohonan.validasi');
        });
    });

    Route::get('/laporan', [DashboardController::class, 'laporan'])->middleware('role:admin_lab,superadmin')->name('laporan');

    // Tim operator per lab: dikelola Admin Lab sendiri (dan Superadmin)
    Route::prefix('tim')->name('tim.')->middleware('role:admin_lab,superadmin')->group(function () {
        Route::get('/', [TimController::class, 'index'])->name('index');
        Route::post('/', [TimController::class, 'store'])->middleware('throttle:aksi')->name('store');
        Route::put('/{user}', [TimController::class, 'update'])->whereNumber('user')->middleware('throttle:aksi')->name('update');
        Route::post('/{user}/reset', [TimController::class, 'resetPassword'])->whereNumber('user')->middleware('throttle:aksi')->name('reset');
        Route::post('/{user}/buka-kunci', [TimController::class, 'unlock'])->whereNumber('user')->middleware('throttle:aksi')->name('unlock');
    });

    // Chat langsung pengguna jasa <-> admin & operator
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/api/chat/status', [ChatController::class, 'status'])->middleware('throttle:chat')->name('chat.status');
    Route::get('/api/chat/{thread}', [ChatController::class, 'pesan'])->whereNumber('thread')->middleware('throttle:chat')->name('chat.pesan');
    Route::post('/api/chat/{thread}', [ChatController::class, 'kirim'])->whereNumber('thread')->middleware('throttle:chat-kirim')->name('chat.kirim');
    Route::get('/riwayat-uji', [DashboardController::class, 'riwayat'])->middleware('role:petugas_lab')->name('riwayat');

    // Superadmin
    Route::prefix('admin')->name('admin.')->middleware('role:superadmin')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('users');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->whereNumber('user')->name('users.update');
        Route::post('/pengguna/{user}/reset', [UserController::class, 'resetPassword'])->whereNumber('user')->name('users.reset');
        Route::post('/pengguna/{user}/buka-kunci', [UserController::class, 'unlock'])->whereNumber('user')->name('users.unlock');
        Route::get('/lab', [LabController::class, 'index'])->name('labs');
        Route::post('/lab', [LabController::class, 'save'])->name('labs.store');
        Route::put('/lab/{lab}', [LabController::class, 'save'])->whereNumber('lab')->name('labs.update');
        Route::get('/pengaturan', [SettingController::class, 'index'])->name('settings');
        Route::put('/pengaturan', [SettingController::class, 'save'])->name('settings.save');
        Route::get('/log-aktivitas', [SettingController::class, 'audit'])->name('audit');
    });
});
