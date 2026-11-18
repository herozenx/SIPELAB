@extends('layouts.app')
@section('title', 'Pengaturan')
@section('content')
<h1>Pengaturan</h1>
<p class="mt-1 text-kabut-600">Format nomor, kota, dan penandatangan dipakai di file LHP. Norma waktu dan hari libur dipakai menghitung batas pengujian.</p>
<form method="post" action="{{ route('admin.settings.save') }}" class="panel mt-6 max-w-2xl" novalidate data-guard>
  @csrf @method('put')
  <div class="panel-body space-y-5">
    <div><label for="nama_instansi" class="label">Nama instansi</label><input id="nama_instansi" name="nama_instansi" value="{{ old('nama_instansi', $s['nama_instansi']) }}" class="input" required></div>
    <div><label for="alamat_instansi" class="label">Alamat instansi</label><input id="alamat_instansi" name="alamat_instansi" value="{{ old('alamat_instansi', $s['alamat_instansi']) }}" class="input" required></div>
    <div><label for="biaya_admin" class="label">Biaya administrasi (Rp)</label><input id="biaya_admin" name="biaya_admin" type="number" min="0" step="1000" value="{{ old('biaya_admin', $s['biaya_admin']) }}" class="input num max-w-xs" required><p class="hint">Ditambahkan sekali per permohonan.</p></div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-5 space-y-5">
    <h2>LHP</h2>
    <div>
      <p class="label">Format nomor LHP per layanan</p>
      <p class="hint !mt-0 mb-3"><span class="kode">{NNNN}</span> nomor urut 4 digit (berlanjut sepanjang tahun, semua layanan), <span class="kode">{BULAN}</span> bulan romawi, <span class="kode">{TAHUN}</span> tahun.</p>
      <div class="space-y-3">
        @foreach (\App\Enums\JenisBayar::cases() as $j)
          @php $k = $j->kunciNomor(); @endphp
          <div class="grid items-center gap-1 sm:grid-cols-[150px_1fr] sm:gap-3">
            <label for="{{ $k }}" class="text-sm font-semibold">{{ $j->label() }} <span class="font-normal text-kabut-500">(SP.{{ $j->kodeSampel() }})</span></label>
            <input id="{{ $k }}" name="{{ $k }}" value="{{ old($k, $s[$k]) }}" class="input num" required>
          </div>
          @error($k)<p class="error sm:ml-[162px]">{{ $message }}</p>@enderror
        @endforeach
      </div>
      <p class="hint">Contoh hasil: {{ strtr($s['nomor_lhp_mandiri'], ['{NNNN}' => '0001', '{BULAN}' => \App\Support\FormatLhp::romawi(now()->month), '{TAHUN}' => now()->year]) }}</p>
    </div>
    <div>
      <label for="kota_lhp" class="label">Kota tanda tangan</label>
      <input id="kota_lhp" name="kota_lhp" value="{{ old('kota_lhp', $s['kota_lhp']) }}" class="input max-w-xs" required>
      <p class="hint">Dicetak di atas tanda tangan, mis. "{{ $s['kota_lhp'] }}, {{ \App\Support\FormatLhp::tanggal(now()) }}".</p>
      @error('kota_lhp')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div>
      <label for="penandatangan" class="label">Penandatangan LHP</label>
      <textarea id="penandatangan" name="penandatangan" rows="6" class="input num" required>{{ old('penandatangan', $s['penandatangan']) }}</textarea>
      <p class="hint">Satu orang per baris dengan format <span class="kode">Nama|NIP</span>. Admin Lab memilih penandatangan dan jabatannya (Kepala UPT atau Penanggung jawab Laboratorium) saat menerbitkan LHP. Orang pertama menjadi pilihan awal.</p>
      @error('penandatangan')<p class="error">{{ $message }}</p>@enderror
    </div>
  </div>
  <div class="border-t border-kabut-200 px-5 py-5 space-y-5">
    <h2>Norma waktu</h2>
    <div>
      <label for="norma_total_hari" class="label">Target sampel jadi (hari kerja)</label>
      <input id="norma_total_hari" name="norma_total_hari" type="number" min="1" max="60" value="{{ old('norma_total_hari', $s['norma_total_hari']) }}" class="input num max-w-[10rem]" required>
      <p class="hint">Dihitung sejak sampel masuk lab pertama. Norma per lab diatur di <a href="{{ route('admin.labs') }}">Lab &amp; tarif</a>.</p>
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
