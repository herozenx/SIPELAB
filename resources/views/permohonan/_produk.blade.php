{{-- Satu blok produk di form pengajuan. $i = indeks (angka, atau "__i__" untuk template), $nilai = isian lama. --}}
@php
  $nilai = $nilai ?? [];
  $dipilih = collect($nilai['parameter'] ?? [])->map(fn ($v) => (int) $v);
  $jumlah = $nilai['jumlah'] ?? 1;
  $id = "produk-{$i}";
@endphp
<section class="panel" data-produk>
  <div class="panel-head">
    <h2>Produk <span data-produk-no>{{ is_int($i) ? $i + 1 : '' }}</span></h2>
    <button type="button" class="btn-ghost btn-sm @if(is_int($i) && $i === 0) hidden @endif" data-produk-hapus><x-ikon n="silang" class="h-4 w-4" />Hapus produk</button>
  </div>
  <div class="panel-body space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
      <div>
        <label for="{{ $id }}-komoditas" class="label">Jenis komoditas</label>
        <input id="{{ $id }}-komoditas" name="produk[{{ $i }}][komoditas]" value="{{ $nilai['komoditas'] ?? '' }}" class="input" placeholder="Contoh: Krustasea" required maxlength="80" list="daftar-komoditas" @if(is_int($i) && $errors->has("produk.{$i}.komoditas")) aria-invalid="true" @endif>
        @if (is_int($i))@error("produk.{$i}.komoditas")<p class="error">{{ $message }}</p>@enderror @endif
      </div>
      <div>
        <label for="{{ $id }}-nama" class="label">Nama/jenis sampel</label>
        <input id="{{ $id }}-nama" name="produk[{{ $i }}][nama]" value="{{ $nilai['nama'] ?? '' }}" class="input" placeholder="Contoh: Udang beku" required maxlength="120" @if(is_int($i) && $errors->has("produk.{$i}.nama")) aria-invalid="true" @endif>
        @if (is_int($i))@error("produk.{$i}.nama")<p class="error">{{ $message }}</p>@enderror @endif
      </div>
      <div>
        <label for="{{ $id }}-jumlah" class="label">Jumlah sampel <span class="font-normal text-kabut-500">(kemasan/unit)</span></label>
        <input id="{{ $id }}-jumlah" name="produk[{{ $i }}][jumlah]" type="number" min="1" max="500" value="{{ $jumlah }}" class="input num" required data-jumlah>
        @if (is_int($i))@error("produk.{$i}.jumlah")<p class="error">{{ $message }}</p>@enderror @endif
      </div>
      <div>
        <label for="{{ $id }}-berat" class="label">Berat sampel</label>
        <input id="{{ $id }}-berat" name="produk[{{ $i }}][berat]" value="{{ $nilai['berat'] ?? '' }}" class="input num" placeholder="Contoh: 500 g atau 1 kg" required maxlength="30" @if(is_int($i) && $errors->has("produk.{$i}.berat")) aria-invalid="true" @endif>
        @if (is_int($i))@error("produk.{$i}.berat")<p class="error">{{ $message }}</p>@enderror @endif
      </div>
    </div>

    <div class="space-y-4">
      <p class="label">Parameter uji <span class="font-normal text-kabut-500">(pilih parameter &amp; jumlah pengujian)</span></p>
      @if (is_int($i))@error("produk.{$i}.parameter")<p class="error">{{ $message }}</p>@enderror @endif
      @forelse ($parameter as $bidang => $list)
        <fieldset>
          <legend class="mb-2 text-xs font-bold uppercase tracking-wide text-kabut-500">{{ $bidang }}</legend>
          <ul class="divide-y divide-kabut-100 rounded-lg border border-kabut-200">
            @foreach ($list as $x)
              <li class="flex flex-wrap items-center gap-3 px-3 py-2.5">
                <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                  <input type="checkbox" name="produk[{{ $i }}][parameter][]" value="{{ $x->id }}" data-param data-tarif="{{ $x->tarif }}" class="mt-1 h-4 w-4 shrink-0 accent-laut-600" @checked($dipilih->contains($x->id))>
                  <span class="min-w-0"><span class="block text-sm font-semibold">{{ $x->nama }}@unless($x->akreditasi)<span class="text-kabut-500" title="Belum terakreditasi"> *</span>@endunless</span><span class="block text-xs text-kabut-500">{{ $x->kode }} · {{ $x->metode }}</span></span>
                </label>
                <span class="num w-24 text-right text-xs text-kabut-500">{{ $x->tarif ? rupiah($x->tarif).'/uji' : 'Tarif menyusul' }}</span>
                <label class="sr-only" for="{{ $id }}-uji-{{ $x->id }}">Jumlah pengujian {{ $x->nama }}</label>
                <input id="{{ $id }}-uji-{{ $x->id }}" name="produk[{{ $i }}][jumlah_uji][{{ $x->id }}]" type="number" min="1" max="500" value="{{ $nilai['jumlah_uji'][$x->id] ?? 1 }}" class="input num w-20 py-1.5" data-param-jumlah title="Jumlah pengujian">
              </li>
            @endforeach
          </ul>
        </fieldset>
      @empty
        <p class="text-sm text-kabut-500">Belum ada parameter uji yang aktif. Hubungi petugas Balai.</p>
      @endforelse
    </div>
  </div>
</section>
