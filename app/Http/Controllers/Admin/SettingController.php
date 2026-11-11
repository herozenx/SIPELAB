<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return view('admin.settings', ['s' => collect(Setting::DEFAULTS)->keys()->mapWithKeys(fn ($k) => [$k => Setting::get($k)])]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'nama_instansi' => ['required', 'string', 'min:3', 'max:200'],
            'alamat_instansi' => ['required', 'string', 'min:3', 'max:300'],
            'biaya_admin' => ['required', 'integer', 'min:0', 'max:100000000'],
            'prefix_lhu' => ['required', 'regex:/^[A-Za-z0-9\-\/]{2,40}$/'],
            'norma_total_hari' => ['required', 'integer', 'min:1', 'max:60'],
            'hari_libur' => ['nullable', 'string', 'max:3000'],
            'whatsapp_center' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
        ], [
            'prefix_lhu.regex' => 'Prefiks hanya boleh huruf, angka, tanda - dan /.',
            'whatsapp_center.regex' => 'Nomor WhatsApp 8–20 digit, boleh memakai + atau -.',
        ]);
        $data['whatsapp_center'] = trim((string) ($data['whatsapp_center'] ?? ''));

        // Hari libur: satu tanggal YYYY-MM-DD per baris (atau dipisah koma); disimpan rapi & urut.
        $tanggal = preg_split('/[\s,;]+/', (string) ($data['hari_libur'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tanggal as $t) {
            $d = \DateTime::createFromFormat('!Y-m-d', $t);
            if (! $d || $d->format('Y-m-d') !== $t) {
                return back()->withInput()->withErrors(['hari_libur' => "Tanggal \"{$t}\" tidak valid. Gunakan format TAHUN-BULAN-TANGGAL, mis. 2026-12-25."]);
            }
        }
        $tanggal = array_values(array_unique($tanggal));
        sort($tanggal);
        $data['hari_libur'] = implode("\n", $tanggal);

        Setting::put($data);
        Audit::log('SETTING_UBAH', 'settings', null, $data);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function audit(Request $request)
    {
        $aksi = (string) $request->query('aksi');
        $q = AuditLog::query()->with('user')->latest('id');
        if ($aksi !== '' && preg_match('/^[A-Z_]{2,40}$/', $aksi)) {
            $q->where('action', $aksi);
        }

        return view('admin.audit', [
            'rows' => $q->paginate(40)->withQueryString(),
            'aksiList' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'aksi' => $aksi,
        ]);
    }
}
