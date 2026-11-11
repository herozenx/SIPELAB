<?php

return [
    // Batas umur sesi sejak login (menit), terlepas dari aktivitas.
    'session_absolute_minutes' => (int) env('SIPELAB_SESSION_ABSOLUTE', 600),

    // Penguncian akun setelah gagal login berturut-turut.
    'lockout_attempts' => 5,
    'lockout_minutes' => 15,

    // Interval pengecekan pembaruan data di browser (detik).
    'poll_seconds' => (int) env('SIPELAB_POLL_SECONDS', 8),

    // Format pembayaran SIMPONI (sesuaikan bila format Balai berbeda).
    'regex_kode_billing' => '/^\d{15}$/',
    'regex_ntpn' => '/^[A-Z0-9]{16}$/',

    // Simpan isi berkas unggahan di database, bukan di disk.
    // Wajib true di hosting tanpa disk permanen (mis. Vercel); di server biasa biarkan false.
    'berkas_di_database' => (bool) env('SIPELAB_BERKAS_DB', false),
];
