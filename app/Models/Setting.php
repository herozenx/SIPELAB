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
        'biaya_admin' => '0', // PNBP SIMPONI hanya memuat tarif parameter
        // Nomor LHP & surat tugas. {urut} = nomor urut agenda per tahun (4 digit), {bulan} = bulan Romawi, {tahun}.
        'format_nomor_lhp' => '{urut}/LAB/BPPMHKP.SBY.II/KI.310/{bulan}/{tahun}',
        // Surat tugas pengujian (template FL/OK/SUB II/8.5.3.d). {kode_sampel} = kode sampel, mis. SP.S/050126.02.
        'format_nomor_surat_tugas' => '{kode_sampel}/FPPCU/SUB II/{bulan}/{tahun}',
        'kota_surat_tugas' => 'Surabaya',
        'kode_formulir_surat_tugas' => 'FL/OK/SUB II/8.5.3.d',
        'jabatan_kepala_lab' => 'Kepala Laboratorium Penguji',
        'nama_kepala_lab' => '',
        'nip_kepala_lab' => '',
        'kota_lhp' => 'Sidoarjo',
        // Bukti Tagihan PNBP
        'satuan_kerja' => 'Balai PPMHKP Surabaya II',
        'kode_formulir_lhp' => 'FL/OK/LP/SUB II/8.6.d',
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
