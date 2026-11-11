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
        'prefix_lhu' => 'LHU/BPPMHKP-SBY2',
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

    public static function put(array $values): void
    {
        foreach ($values as $k => $v) {
            static::query()->updateOrCreate(['key' => $k], ['value' => (string) $v]);
        }
        Cache::forget('sipelab.settings');
        HariKerja::lupakan();
    }
}
