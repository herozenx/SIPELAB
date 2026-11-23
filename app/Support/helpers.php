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

if (! function_exists('terbilang')) {
    /** Angka ke kata Bahasa Indonesia, mis. 500000 => "Lima Ratus Ribu Rupiah". */
    function terbilang(int $n, bool $rupiah = true): string
    {
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $kata = function (int $x) use (&$kata, $satuan): string {
            return match (true) {
                $x < 12 => $satuan[$x],
                $x < 20 => $kata($x - 10).' belas',
                $x < 100 => $kata(intdiv($x, 10)).' puluh '.$kata($x % 10),
                $x < 200 => 'seratus '.$kata($x - 100),
                $x < 1000 => $kata(intdiv($x, 100)).' ratus '.$kata($x % 100),
                $x < 2000 => 'seribu '.$kata($x - 1000),
                $x < 1000000 => $kata(intdiv($x, 1000)).' ribu '.$kata($x % 1000),
                $x < 1000000000 => $kata(intdiv($x, 1000000)).' juta '.$kata($x % 1000000),
                default => $kata(intdiv($x, 1000000000)).' miliar '.$kata($x % 1000000000),
            };
        };
        $teks = $n === 0 ? 'nol' : $kata(abs($n));
        $teks = ucwords(trim(preg_replace('/\s+/', ' ', $teks)));

        return $rupiah ? $teks.' Rupiah' : $teks;
    }
}
