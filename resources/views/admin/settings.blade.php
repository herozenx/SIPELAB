@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
<h1>Pengaturan</h1>
<p class="mt-1 text-kabut-600">Identitas instansi dan format nomor dicetak di LHP dan surat tugas. Norma waktu dan hari libur dipakai menghitung batas pengujian.</p>
<form method="post" action="{{ route('admin.settings.save') }}" class="panel mt-6 max-w-2xl" novalidate data-guard>
  @csrf @method('put')
  <div class="panel-body space-y-5">
    <div><label for="nama_instansi" class="label">Nama instansi</label><input id="nama_instansi" name="nama_instansi" value="{{ old('nama_instansi', $s['nama_instansi']) }}" class="input" required></div>
    <div><label for="alamat_instansi" class="label">Alamat instansi</label><input id="alamat_instansi" name="alamat_instansi" value="{{ old('alamat_instansi', $s['alamat_instansi']) }}" class="input" required></div>
    <div class="grid gap-5 sm:grid-cols-2">
      <div><label for="biaya_admin" class="label">Biaya administrasi (Rp)</label><input id="biaya_admin" name="biaya_admin" type="number" min="0" step="1000" value="{{ old('biaya_admin', $s['biaya_admin']) }}" class="input num" required><p class="hint">Ditambahkan sekali per permohonan.</p></div>
      <div><label for="kota_lhp" class="label">Kota penerbitan LHP</label><input id="kota_lhp" name="kota_lhp" value="{{ old('kota_lhp', $s['kota_lhp']) }}" class="input" required><p class="hint">Dicetak sebelum tanggal, mis. "Sidoarjo, 19 November 2026".</p></div>
    </div>
    @php $wf = app(\App\Services\Workflow::class); @endphp
    <div><label for="format_nomor_lhp" class="label">Format nomor LHP</label><input id="format_nomor_lhp" name="format_nomor_lhp" value="{{ old('format_nomor_lhp', $s['format_nomor_lhp']) }}" class="input num" required><p class="hint">{urut} = nomor urut agenda per tahun (4 digit), {bulan} = bulan Romawi, {tahun}. Contoh: <span class="num">{{ $wf->formatNomor($s['format_nomor_lhp'], 1, now()) }}</span></p>@error('format_nomor_lhp')<p class="error">{{ $message }}</p>@enderror</div>
    <div><label for="kode_formulir_lhp" class="label">Kode formulir LHP</label><input id="kode_formulir_lhp" name="kode_formulir_lhp" value="{{ old('kode_formulir_lhp', $s['kode_formulir_lhp']) }}" class="input num max-w-xs" required><p class="hint">Dicetak di kaki LHP.</p></div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-5 space-y-5">
    <h2>Surat tugas pengujian</h2>
    <div><label for="format_nomor_surat_tugas" class="label">Format No. FPPC</label><input id="format_nomor_surat_tugas" name="format_nomor_surat_tugas" value="{{ old('format_nomor_surat_tugas', $s['format_nomor_surat_tugas']) }}" class="input num" required><p class="hint">{kode_sampel} = kode sampel, {bulan} = bulan Romawi, {tahun}. Contoh: <span class="num">{{ $wf->formatNomor($s['format_nomor_surat_tugas'], 1, now(), ['{kode_sampel}' => 'SP.S/050126.02']) }}</span></p>@error('format_nomor_surat_tugas')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="grid gap-5 sm:grid-cols-2">
      <div><label for="jabatan_kepala_lab" class="label">Jabatan penanda tangan</label><input id="jabatan_kepala_lab" name="jabatan_kepala_lab" value="{{ old('jabatan_kepala_lab', $s['jabatan_kepala_lab']) }}" class="input" required><p class="hint">Mis. "Kepala/Penanggung Jawab Laboratorium Pengujian". Nama penanda tangan dipilih saat surat tugas diterbitkan.</p></div>
      <div><label for="kota_surat_tugas" class="label">Kota</label><input id="kota_surat_tugas" name="kota_surat_tugas" value="{{ old('kota_surat_tugas', $s['kota_surat_tugas']) }}" class="input" required></div>
      <div><label for="nama_kepala_lab" class="label">Nama penanda tangan</label><input id="nama_kepala_lab" name="nama_kepala_lab" value="{{ old('nama_kepala_lab', $s['nama_kepala_lab']) }}" class="input"><p class="hint">Pilihan bawaan penanda tangan surat tugas. Akun Penanggung Jawab Lab yang aktif ikut menjadi pilihan.</p></div>
      <div><label for="nip_kepala_lab" class="label">NIP penanda tangan</label><input id="nip_kepala_lab" name="nip_kepala_lab" value="{{ old('nip_kepala_lab', $s['nip_kepala_lab']) }}" class="input num">@error('nip_kepala_lab')<p class="error">{{ $message }}</p>@enderror</div>
    </div>
    <div><label for="kode_formulir_surat_tugas" class="label">Kode formulir surat tugas</label><input id="kode_formulir_surat_tugas" name="kode_formulir_surat_tugas" value="{{ old('kode_formulir_surat_tugas', $s['kode_formulir_surat_tugas']) }}" class="input num max-w-xs" required></div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-5 space-y-5">
    <h2>Norma waktu</h2>
    <div>
      <label for="norma_total_hari" class="label">Target sampel jadi (hari kerja)</label>
      <input id="norma_total_hari" name="norma_total_hari" type="number" min="1" max="60" value="{{ old('norma_total_hari', $s['norma_total_hari']) }}" class="input num max-w-[10rem]" required>
      <p class="hint">Dihitung sejak sampel masuk lab pertama. Norma per lab diatur di <a href="{{ route('admin.labs') }}">Laboratorium</a>.</p>
      @error('norma_total_hari')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
      <label for="hari_libur" class="label">Hari libur nasional / cuti bersama</label>
      <textarea id="hari_libur" name="hari_libur" rows="6" class="input num" placeholder="2026-12-25&#10;2027-01-01">{{ old('hari_libur', $s['hari_libur']) }}</textarea>
      <p class="hint">Satu tanggal per baris, format TAHUN-BULAN-TANGGAL. Sabtu dan Minggu otomatis tidak dihitung.</p>
      @error('hari_libur')<p class="error">{{ $message }}</p>@enderror
    </div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-5 space-y-5">
    <h2>Bantuan</h2>
    <div>
      <label for="whatsapp_center" class="label">Nomor WhatsApp Center <span class="font-normal text-kabut-500">(opsional)</span></label>
      <input id="whatsapp_center" name="whatsapp_center" value="{{ old('whatsapp_center', $s['whatsapp_center']) }}" class="input num max-w-xs" inputmode="tel" placeholder="0853-xxxx-xxxx">
      <p class="hint">Ditampilkan sebagai tombol "Chat WhatsApp" di Pusat bantuan, Panduan, dan halaman Chat (cadangan bila tidak ada petugas online). Kosongkan untuk menyembunyikan.</p>
      @error('whatsapp_center')<p class="error">{{ $message }}</p>@enderror
    </div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-4"><button class="btn-primary">Simpan pengaturan</button></div>
</form>
@endsection
