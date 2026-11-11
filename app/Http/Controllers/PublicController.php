<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\Lhu;
use App\Models\Setting;
use App\Services\Workflow;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', [
            'labs' => Lab::query()->aktif()->get(),
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
        ]);
    }

    /** Panduan pemakaian untuk pengguna jasa (publik; tampil di dalam menu bila sudah masuk). */
    public function panduan()
    {
        return view('public.panduan', [
            'labs' => Lab::query()->aktif()->get(),
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
            'normaTotal' => (int) Setting::get('norma_total_hari'),
        ]);
    }

    /** Cek keaslian LHU (publik, tanpa data pribadi pemohon). */
    public function verifikasi(Request $request, Workflow $wf, ?string $kode = null)
    {
        $kode = strtoupper(trim((string) ($kode ?? $request->query('kode'))));
        $lhu = null;
        $err = null;
        $utuh = null;

        if ($kode !== '') {
            if (! preg_match('/^[A-F0-9]{12}$/', $kode)) {
                $err = 'Kode verifikasi terdiri dari 12 karakter (angka 0–9 dan huruf A–F). Periksa kembali kode yang tercetak di LHU.';
            } else {
                $lhu = Lhu::query()->with('permohonan')->where('kode_verifikasi', $kode)->whereNotNull('nomor')->first();
                if ($lhu) {
                    $utuh = $wf->lhuUtuh($lhu);
                } else {
                    $err = 'Tidak ada LHU dengan kode ini. Dokumen yang Anda pegang kemungkinan tidak diterbitkan oleh Balai.';
                }
            }
        }

        return view('public.verifikasi', compact('kode', 'lhu', 'err', 'utuh'));
    }
}
