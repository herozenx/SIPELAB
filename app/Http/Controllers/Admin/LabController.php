<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Models\Lab;
use App\Models\Permohonan;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabController extends Controller
{
    public function index()
    {
        return view('admin.labs', [
            'labs' => Lab::query()->orderBy('urutan')->get(),
            'biayaAdmin' => (int) Setting::get('biaya_admin'),
        ]);
    }

    public function save(Request $request, ?Lab $lab = null)
    {
        $request->merge(['aktif' => $request->boolean('aktif')]);
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:60', Rule::unique('labs', 'nama')->ignore($lab?->id)],
            'urutan' => ['required', 'integer', 'min:1', 'max:99'],
            'tarif' => ['required', 'integer', 'min:0', 'max:100000000'],
            'norma_hari' => ['required', 'integer', 'min:0', 'max:30'],
            'aktif' => ['boolean'],
        ]);

        // Lab yang masih memegang sampel tidak boleh dinonaktifkan (sampel akan "hilang" dari antrean).
        if ($lab && $lab->aktif && ! $data['aktif']
            && Permohonan::query()->where('status', Status::DiLab->value)->where('current_lab_id', $lab->id)->exists()) {
            return back()->with('error', "Lab {$lab->nama} masih memegang sampel. Selesaikan antreannya dulu sebelum menonaktifkan.");
        }

        if ($lab) {
            $lab->update($data);
            Audit::log('LAB_UBAH', 'lab', $lab->id, $data);
        } else {
            $lab = Lab::query()->create($data);
            Audit::log('LAB_TAMBAH', 'lab', $lab->id, $data);
        }

        return redirect()->route('admin.labs')->with('success', "Lab {$lab->nama} disimpan. Tarif baru berlaku untuk tagihan berikutnya.");
    }
}
