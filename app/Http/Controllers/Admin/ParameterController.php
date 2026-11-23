<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\ParameterUji;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master parameter uji (FPPCU) beserta tarif per pengujian. Perubahan hanya berlaku untuk permohonan baru. */
class ParameterController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.parameter', [
            'rows' => ParameterUji::query()->with('lab')->orderBy('urutan')->orderBy('id')->get()->groupBy('bidang'),
            'labs' => Lab::query()->orderBy('urutan')->get(),
            'edit' => $request->query('edit') ? ParameterUji::query()->find((int) $request->query('edit')) : null,
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
        ]);
    }

    public function save(Request $request, ?ParameterUji $parameter = null)
    {
        $request->merge([
            'aktif' => $request->boolean('aktif'), 'akreditasi' => $request->boolean('akreditasi'),
            'kode' => strtoupper(trim((string) $request->input('kode'))),
            'tarif' => preg_replace('/\D+/', '', (string) $request->input('tarif')),
        ]);
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[A-Z0-9\-]{1,10}$/', Rule::unique('parameter_uji', 'kode')->ignore($parameter?->id)],
            'nama' => ['required', 'string', 'min:2', 'max:120'],
            'bidang' => ['required', 'string', 'min:2', 'max:40'],
            'lab_id' => ['required', 'exists:labs,id'],
            'metode' => ['required', 'string', 'min:1', 'max:150'],
            'persyaratan' => ['nullable', 'string', 'max:150'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'tarif' => ['required', 'integer', 'min:0', 'max:100000000'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'akreditasi' => ['boolean'],
            'aktif' => ['boolean'],
        ], ['kode.regex' => 'Kode parameter 1–10 huruf kapital/angka, mis. E1 atau AMOZ.']);

        if ($parameter) {
            $parameter->update($data);
            Audit::log('PARAMETER_UBAH', 'parameter', $parameter->id, $data);
        } else {
            $parameter = ParameterUji::query()->create($data);
            Audit::log('PARAMETER_TAMBAH', 'parameter', $parameter->id, $data);
        }

        return redirect()->route('admin.parameter')->with('success', "Parameter {$parameter->kode} – {$parameter->nama} disimpan. Berlaku untuk permohonan berikutnya.");
    }
}
