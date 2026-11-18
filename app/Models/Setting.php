<?php

namespace App\Models;

use App\Support\HariKerja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const DEFAULTS = [
        'nama_instansi' => 'Balai Pengendalian dan Pengawasan Mutu Hasil Kelautan dan Perikanan Surabaya II',
        'alamat_instansi' => 'Jl. Sawunggaling 177-183, Jemundo, Taman, Sidoarjo',
        'biaya_admin' => '10000',
        // Format nomor LHP per layanan (sesuai LHP asli instansi). {NNNN} urut per tahun, {BULAN} romawi, {TAHUN}.
        'nomor_lhp_mandiri' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        'nomor_lhp_surveilen' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        'nomor_lhp_eksternal' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        'nomor_lhp_monitoring' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        'nomor_lhp_test_result' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        'nomor_lhp_uji_banding' => '{NNNN}/LAB/BPPMHKP.SBY.II/KI.310/{BULAN}/{TAHUN}',
        // Kota pada tanggal tanda tangan LHP ("Sidoarjo, 21 September 2026").
        'kota_lhp' => 'Sidoarjo',
        // Penandatangan LHP: satu orang per baris "Nama|NIP" (dari LHP asli).
        'penandatangan' => "Dyah Setyawati, S.Pi., MP.|19780207 200212 2 001\nZaki Muhammad Wijaya, S.Pi, M.Si|19830813 200912 1 001\nLaminem, S.Pi., MP.|19701206 199203 2 002\nEvy Riesmania R, S.Pi|19800205 200312 2 006\nAnita Tri Widyayanti, S.Pi|19780320 200604 2 002\nDetrich Nurul A., S.Si|19800131 200502 2 008",
        // Norma waktu sampel jadi (hari kerja) sejak masuk lab pertama sampai LHU divalidasi.
        'norma_total_hari' => '6',
        // Tanggal libur nasional/cuti bersama (YYYY-MM-DD), dipisah baris/koma. Sabtu-Minggu otomatis libur.
        'hari_libur' => '',
        // Nomor WhatsApp Center Balai (cadangan chat web). Kosong = tidak ditampilkan.
        'whatsapp_center' => '',
    ];

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key): string
    {
        $all = Cache::rememberForever('sipelab.settings', fn () => static::query()->pluck('value', 'key')->all());

        return (string) ($all[$key] ?? self::DEFAULTS[$key] ?? '');
    }

    /** @return array<int, array{nama:string, nip:string}> daftar penandatangan LHP dari pengaturan */
    public static function penandatangan(): array
    {
        $out = [];
        foreach (preg_split('/\R/', static::get('penandatangan')) ?: [] as $baris) {
            [$nama, $nip] = array_map('trim', explode('|', $baris, 2)) + [1 => ''];
            if ($nama !== '') {
                $out[] = ['nama' => $nama, 'nip' => $nip];
            }
        }

        return $out;
    }

    public static function put(array $values): void
    {
        foreach ($values as $k => $v) {
            static::query()->updateOrCreate(['key' => $k], ['value' => (string) $v]);
        }
        Cache::forget('sipelab.settings');
        HariKerja::lupakan();
    }
}
