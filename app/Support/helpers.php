<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $n): string
    {
        return 'Rp'.number_format((float) $n, 0, ',', '.');
    }
}

if (! function_exists('tanggal')) {
    /** Tanggal format Indonesia, mis. 10 Nov 2026 14.05 */
    function tanggal($dt, bool $jam = true): string
    {
        if (! $dt) {
            return '–';
        }
        $c = $dt instanceof \Carbon\CarbonInterface ? $dt : \Illuminate\Support\Carbon::parse($dt);
        $c = $c->copy()->setTimezone(config('app.timezone'));
        $bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$c->month - 1];

        return $c->day.' '.$bln.' '.$c->year.($jam ? ' '.$c->format('H.i') : '');
    }
}

if (! function_exists('wa_link')) {
    /** Tautan wa.me ke WhatsApp Center Balai (dari Pengaturan); null bila belum diisi. */
    function wa_link(?string $pesan = null): ?string
    {
        $no = preg_replace('/\D+/', '', \App\Models\Setting::get('whatsapp_center'));
        if ($no === '' || strlen($no) < 8) {
            return null;
        }
        if (str_starts_with($no, '0')) {
            $no = '62'.substr($no, 1);
        }

        return 'https://wa.me/'.$no.($pesan ? '?text='.rawurlencode($pesan) : '');
    }
}

if (! function_exists('ukuran_berkas')) {
    function ukuran_berkas(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : max(1, (int) round($bytes / 1024)).' KB';
    }
}
