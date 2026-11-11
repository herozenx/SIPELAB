<?php

/*
 * Pintu masuk untuk Vercel (runtime komunitas vercel-php).
 * Semua permintaan non-statis diteruskan ke Laravel seperti public/index.php.
 * Di server biasa file ini tidak dipakai.
 */

// Vercel hanya bisa menulis ke /tmp: siapkan folder kerja Laravel di sana.
foreach (['/tmp/views', '/tmp/storage/framework/cache/data', '/tmp/storage/logs'] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Laravel menebak "base path" dari SCRIPT_NAME. Karena berkas ini ada di /api/index.php,
// rute seperti /api/chat/... akan terbaca tanpa "/api". Samakan dengan server biasa (public/index.php).
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

try {
    require __DIR__.'/../public/index.php';
} catch (\Throwable $e) {
    // Log Vercel memotong pesan panjang: catat rantai penyebab dalam satu baris pendek.
    $rantai = [];
    for ($x = $e; $x; $x = $x->getPrevious()) {
        $rantai[] = get_class($x).': '.mb_substr($x->getMessage(), 0, 240).' @ '.basename($x->getFile()).':'.$x->getLine();
    }
    error_log('SIPELAB-FATAL '.implode(' <= ', $rantai));
    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
}
