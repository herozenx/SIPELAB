<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\ParameterUji;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Superadmin: katalog parameter uji (kolom Buku Sampel) beserta teks persyaratan & metode di LHP. */
class ParameterController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.parameter', [
            'labs' => Lab::query()->orderBy('urutan')->get(),
            'grup' => ParameterUji::query()->orderBy('urutan')->get()->groupBy('lab_id'),
            'edit' => $request->query('edit') ? ParameterUji::query()->find((int) $request->query('edit')) : null,
        ]);
    }

    public function save(Request $request, ?ParameterUji $parameter = null)
    {
        $request->merge(['aktif' => $request->boolean('aktif')]);
        $data = $request->validate([
            'lab_id' => ['required', 'exists:labs,id'],
            'kelompok' => ['required', 'string', 'max:30'],
            'kode' => ['required', 'string', 'max:12', Rule::unique('parameter_uji')->where(fn ($q) => $q->where('lab_id', $request->input('lab_id'))->where('kelompok', $request->input('kelompok')))->ignore($parameter?->id)],
            'bidang' => ['required', 'string', 'max:40'],
            'bidang_en' => ['required', 'string', 'max:40'],
            'nama' => ['required', 'string', 'max:200'],
            'satuan' => ['nullable', 'string', 'max:30'],
            'pilihan' => ['nullable', 'string', 'max:1000'],
            'persyaratan' => ['nullable', 'string', 'max:2000'],
            'metode' => ['nullable', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:150'],
            'ulangan' => ['required', 'integer', 'min:1', 'max:20'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'aktif' => ['boolean'],
        ], ['kode.unique' => 'Kode ini sudah dipakai di kelompok yang sama.']);

        $baris = fn (?string $v) => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $v) ?: []), fn ($x) => $x !== ''));
        $data['pilihan'] = $baris($data['pilihan'] ?? null);
        $data['persyaratan'] = $baris($data['persyaratan'] ?? null);
        $data['kelompok'] = mb_strtoupper(trim($data['kelompok']));
        $data['kode'] = mb_strtoupper(trim($data['kode']));
        foreach (['metode', 'keterangan'] as $k) {
            $data[$k] = isset($data[$k]) && trim($data[$k]) !== '' ? str_replace("\r\n", "\n", trim($data[$k])) : null;
        }

        if ($parameter) {
            $parameter->update($data);
            Audit::log('PARAMETER_UBAH', 'parameter_uji', $parameter->id, ['nama' => $data['nama']]);
        } else {
            $parameter = ParameterUji::query()->create($data);
            Audit::log('PARAMETER_TAMBAH', 'parameter_uji', $parameter->id, ['nama' => $data['nama']]);
        }

        return redirect()->route('admin.parameter')->with('success', "Parameter {$parameter->namaPolos()} disimpan. Berlaku untuk isian berikutnya; LHP yang sudah terbit tidak berubah.");
    }
}
