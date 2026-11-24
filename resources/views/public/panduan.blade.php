@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Panduan penggunaan')
@section('content')
@php
  $masuk = auth()->check();
  $wa = wa_link('Halo Balai, saya ingin bertanya tentang layanan uji SILANTRO.');
  $labNama = $labs->pluck('nama')->join(' → ');
  /*
   * Setiap bab: id, judul, ikon, pengantar, langkah (boleh <b>), catatan [jenis, teks], khusus (blok tambahan).
   * Teks ditulis tim Balai; ubah di sini untuk memperbarui panduan.
   */
  $bab = [
    ['mengenal', 'Mengenal SILANTRO', 'beranda',
      'Gunakan satu akun untuk mengajukan uji, membayar, memantau status, dan menerima LHP.',
      [
        'Pilih <b>Daftar</b> atau <b>Masuk</b> di halaman depan.',
        'Setelah masuk, gunakan <b>Beranda</b> untuk melihat tindakan yang perlu dilakukan.',
        'Gunakan <b>Permohonan saya</b> untuk membuka status dan dokumen.',
      ],
      [['info', 'Di HP, semua menu tersedia setelah menekan <b>Menu</b>.']], null],
    ['akun', 'Akun dan Jenis Layanan', 'akun',
      'Buat akun lalu pilih jenis layanan yang diinginkan',
      [
        'Isi nama penanggung jawab, nama usaha/instansi, email, telepon, dan kata sandi.',
        'Permohonan online umumnya diajukan sebagai <b>Mandiri</b>: bayar sebelum pengujian.',
        'Pengujian Eksternal atau Surveilen ditetapkan petugas Balai dan dibayar setelah LHP terbit.',
      ],
      [['info', 'Nama usaha/instansi dicetak sebagai pemohon pada dokumen resmi.']], null],
    ['ajukan', 'Ajukan dan serahkan sampel', 'tambah',
      'Isi permohonan, lalu bawa sampel fisik ke loket Balai.',
      [
        'Klik <b>Ajukan uji</b>, lalu isi data sampel, komoditas, jumlah, asal, tujuan, dan alamat. Menguji beberapa produk sekaligus? Klik <b>Tambah produk</b>.',
        'Pilih parameter uji dan lampiran pendukung bila ada.',
        'Setelah dikirim, bawa sampel ke loket dan sebutkan kode permohonan.',
        'Petugas memverifikasi, meregistrasi sampel, dan menerbitkan kode sampel atau billing bila diperlukan.',
      ],
      [['info', 'Dokumen pendukung boleh berupa PDF, JPG, atau PNG, maksimal 5 MB per berkas.']], null],
    ['bayar', 'Bayar dan unggah bukti', 'berkas',
      'Bayar melalui SIMPONI sesuai jenis layanan, lalu unggah bukti penyetoran.',
      [
        'Buka permohonan yang berstatus <b>Menunggu Pembayaran</b>.',
        'Catat kode billing dan jumlah setor, lalu bayar melalui bank, ATM, mobile banking, atau kantor pos.',
        'Unggah bukti setor dan kirimkan sebagai bukti pembayaran. Nomor NTPN tidak perlu diketik.',
        'Tunggu petugas Bendahara mengonfirmasi penerimaan dana.',
      ],
      [['info', 'Jika bukti ditolak, alasan ditampilkan pada permohonan dan bukti dapat diunggah ulang.']], null],
    ['pantau', 'Pantau status dan terima LHP', 'jam',
      'Status terbaru ditampilkan di halaman permohonan.',
      [
        'Periksa status: verifikasi, pembayaran, pengujian, penyusunan LHP, atau LHP selesai.',
        'Gunakan <b>Perkiraan Selesai</b> sebagai target waktu dalam hari kerja.',
        'Setelah LHP disahkan, buka atau unduh LHP PDF dari permohonan.',
        'Untuk memeriksa keaslian, pindai QR Code atau masukkan 12 karakter kode verifikasi.',
      ],
      [['info', 'Hari kerja adalah Senin sampai Jumat, di luar hari libur.']], 'norma'],
    ['bantuan', 'Bantuan dan pertanyaan', 'chat',
      'Gunakan pertanyaan yang paling sesuai, atau hubungi petugas.',
      [],
      [], 'faq'],
  ];
  $n = count($bab);
  $faq = [
    ['Berapa lama sampai LHP selesai?', "Target pemeriksaan ditampilkan di permohonan. Batas norma total saat ini adalah {$normaTotal} hari kerja."],
    ['Mengapa hasil uji belum tampil?', 'Hasil uji ditampilkan setelah pengujian dan penyusunan LHP disahkan.'],
    ['Jenis berkas yang bisa diunggah?', 'PDF, JPG, atau PNG, maksimal 5 MB untuk setiap berkas.'],
    ['Lupa kata sandi?', 'Hubungi petugas Balai untuk mereset kata sandi, lalu gantikan kata sandi saat masuk kembali.'],
  ];
  $istilah = [
    ['SIMPONI', 'Sistem Informasi PNBP Online milik Kementerian Keuangan untuk menyetor tagihan layanan.'],
    ['Kode billing', '15 digit kode tagihan SIMPONI yang dipakai saat membayar.'],
    ['Kode sampel', 'Kode registrasi dari buku sampel, mis. SP.M/221126.01 (jenis layanan / tanggal diterima . nomor urut harian; akhiran A, B, C untuk tiap produk bila lebih dari satu).'],
    ['LHP', 'Laporan Hasil Pengujian, dokumen resmi hasil pengujian sampel.'],
  ];
  $catatanGaya = ['info' => ['bg-laut-50 text-laut-700', 'info', 'Info'], 'penting' => ['bg-surya-50 text-surya-700', 'info', 'Penting']];
@endphp

<div class="{{ $masuk ? '' : 'mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12' }}">
  {{-- Kepala panduan --}}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-bold text-laut-600">Panduan Pengguna Jasa</p>
      <h1 class="mt-1">Cara menggunakan SILANTRO</h1>
      <p class="mt-2 max-w-prose text-kabut-600">Pilih satu bagian untuk melihat langkah yang diperlukan.</p>
    </div>
    <div class="no-print flex flex-wrap gap-2">
      <a href="{{ $masuk ? route('dashboard') : route('home') }}" class="btn-ghost"><x-ikon n="kembali" class="h-4 w-4" />{{ $masuk ? 'Beranda' : 'Kembali Ke Beranda' }}</a>
      <button type="button" class="btn-primary" data-print><x-ikon n="unggah" class="h-4 w-4 rotate-180" />Unduh PDF</button>
    </div>
  </div>

  <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[270px_minmax(0,1fr)]">
    {{-- Daftar bagian --}}
    <nav class="no-print panel min-w-0 self-start overflow-hidden lg:sticky lg:top-24" aria-label="Daftar bagian panduan">
      <div class="panel-head"><h2 class="text-base">Daftar Panduan</h2><span class="num text-xs text-kabut-500" data-bab-pos>1 / {{ $n }}</span></div>
      <ol class="flex gap-1 overflow-x-auto p-2 lg:block">
        @foreach ($bab as $i => [$id, $judul])
          <li class="shrink-0 lg:w-full"><a href="#{{ $id }}" data-bab-link class="flex min-h-[44px] items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm text-tinta no-underline hover:bg-kabut-50 hover:no-underline aria-[current=true]:bg-laut-50 aria-[current=true]:font-semibold aria-[current=true]:text-laut-700 lg:whitespace-normal"><span class="num w-5 shrink-0 text-xs font-bold text-kabut-400">{{ sprintf('%02d', $i + 1) }}</span><span>{{ $judul }}</span></a></li>
        @endforeach
      </ol>
    </nav>

    {{-- Isi: satu bagian per halaman (tanpa JavaScript: semua bagian tampil berurutan) --}}
    <div data-panduan class="scroll-mt-24">
      <div class="no-print mb-4 hidden flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 shadow-[0_0_0_1px_theme(colors.kabut.200)]" data-bab-bar>
        <p class="min-w-0 text-sm"><span class="font-semibold" data-bab-judul></span> <span class="text-kabut-500">· Halaman <span class="num" data-bab-pos>1 / {{ $n }}</span></span></p>
        <div class="flex gap-2">
          <button type="button" class="btn-ghost btn-sm" data-bab-prev aria-label="Bagian sebelumnya"><x-ikon n="kembali" class="h-4 w-4" /></button>
          <button type="button" class="btn-ghost btn-sm" data-bab-next aria-label="Bagian berikutnya"><x-ikon n="kembali" class="h-4 w-4 rotate-180" /></button>
        </div>
      </div>

      @foreach ($bab as $i => [$id, $judul, $ikon, $pengantar, $langkah, $catatan, $khusus])
        <article id="{{ $id }}" data-bab data-judul="{{ $judul }}" class="panel mb-6 scroll-mt-24 overflow-hidden print:!block print:break-after-page print:shadow-none">
          <header class="flex items-start gap-4 border-b border-kabut-200 bg-kabut-50 px-5 py-5 sm:px-7">
            <span class="relative grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-laut-600 text-white"><x-ikon :n="$ikon" class="h-6 w-6" /></span>
            <div class="min-w-0">
              <p class="num text-xs font-bold text-laut-600">Bagian {{ sprintf('%02d', $i + 1) }} dari {{ $n }}</p>
              <h2 class="mt-0.5 text-xl">{{ $judul }}</h2>
              <p class="mt-1 text-sm text-kabut-600">{{ $pengantar }}</p>
            </div>
          </header>

          <div class="space-y-6 px-5 py-6 sm:px-7">
            @if ($langkah)
              <ol class="space-y-3">
                @foreach ($langkah as $j => $l)
                  <li class="flex items-start gap-3"><span class="num grid h-7 w-7 shrink-0 place-items-center rounded-full bg-laut-50 text-xs font-bold text-laut-700">{{ $j + 1 }}</span><p class="pt-1 text-sm leading-relaxed [&_b]:font-semibold">{!! $l !!}</p></li>
                @endforeach
              </ol>
            @endif

            @switch ($khusus)
              @case('jenis')
                <div class="grid gap-4 md:grid-cols-3">
                  @foreach ([
                    ['Mandiri', 'Diajukan online · bayar di awal', ['Ajukan permohonan online', 'Sampel diregistrasi, kode SP.M', 'Bayar billing SIMPONI, unggah bukti', 'Sampel diuji di lab', 'LHP selesai, unduh PDF'], 'bg-laut-600'],
                    ['Pengujian Eksternal', 'Ditetapkan petugas · bayar di belakang', ['Permohonan diterima Balai', 'Sampel diverifikasi, kode SP.PE', 'Sampel langsung diuji di lab', 'LHP disahkan, billing terbit', 'Bayar, lalu unduh LHP PDF'], 'bg-lamun-600'],
                    ['Surveilen', 'Ditetapkan petugas · bayar di belakang', ['Permohonan diterima Balai', 'Sampel diverifikasi, kode SP.S', 'Sampel langsung diuji di lab', 'LHP disahkan, billing terbit', 'Bayar, lalu unduh LHP PDF'], 'bg-laut-800'],
                  ] as [$nama, $sub, $alur, $warna])
                    <div class="overflow-hidden rounded-xl ring-1 ring-kabut-200">
                      <div class="{{ $warna }} px-4 py-3 text-white"><p class="font-extrabold">{{ $nama }}</p><p class="text-xs opacity-90">{{ $sub }}</p></div>
                      <ol class="space-y-2 px-4 py-4 text-sm">
                        @foreach ($alur as $k => $a)<li class="flex gap-2"><span class="num w-5 shrink-0 font-bold text-kabut-400">{{ $k + 1 }}.</span>{{ $a }}</li>@endforeach
                      </ol>
                    </div>
                  @endforeach
                </div>
                @break
              @case('norma')
                <div class="overflow-hidden rounded-xl ring-1 ring-kabut-200">
                  <table class="tabel">
                    <tbody>
                      <tr><td class="font-semibold">Norma waktu sampel jadi</td><td class="num text-right font-semibold">{{ $normaTotal }} hari kerja</td></tr>
                      <tr><td class="text-kabut-500">Biaya administrasi per permohonan</td><td class="num text-right text-kabut-500">{{ rupiah($biayaAdmin) }}</td></tr>
                    </tbody>
                  </table>
                </div>
                @break
              @case('bantuan')
                <div class="flex flex-wrap gap-2">
                  <a href="{{ $masuk ? route('chat') : route('login') }}" class="btn-primary"><x-ikon n="chat" class="h-4 w-4" />Chat petugas</a>
                  @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost"><x-ikon n="chat" class="h-4 w-4" />Chat WhatsApp Center</a>@endif
                  <a href="{{ route('verifikasi') }}" class="btn-ghost"><x-ikon n="perisai" class="h-4 w-4" />Cek keaslian LHP</a>
                </div>
                @break
              @case('faq')
                <div class="flex flex-wrap gap-2">
                  <a href="{{ $masuk ? route('chat') : route('login') }}" class="btn-primary min-h-[44px]"><x-ikon n="chat" class="h-4 w-4" />{{ $masuk ? 'Chat petugas' : 'Masuk untuk chat petugas' }}</a>
                  @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="btn-ghost min-h-[44px]"><x-ikon n="chat" class="h-4 w-4" />WhatsApp Center</a>@endif
                </div>
                <div class="divide-y divide-kabut-100 rounded-xl ring-1 ring-kabut-200">
                  @foreach ($faq as [$tanya, $jawab])
                    <details class="group px-4 py-3.5" @if($loop->first) open @endif>
                      <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold [&::-webkit-details-marker]:hidden">{{ $tanya }}<x-ikon n="tambah" class="h-4 w-4 shrink-0 text-kabut-500 transition-transform group-open:rotate-45" /></summary>
                      <p class="mt-2 text-sm text-kabut-600">{{ $jawab }}</p>
                    </details>
                  @endforeach
                </div>
                <div>
                  <h3>Istilah</h3>
                  <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($istilah as [$t, $arti])
                      <div><dt class="font-semibold">{{ $t }}</dt><dd class="text-kabut-600">{{ $arti }}</dd></div>
                    @endforeach
                  </dl>
                </div>
                @break
            @endswitch

            @foreach ($catatan as [$jenis, $teks])
              @php [$cls, $ik, $lbl] = $catatanGaya[$jenis]; @endphp
              <div class="flex gap-3 rounded-lg px-4 py-3 text-sm {{ $cls }}"><x-ikon :n="$ik" class="mt-0.5 h-4 w-4 shrink-0" /><p class="[&_b]:font-semibold"><span class="font-bold">{{ $lbl }}:</span> {!! $teks !!}</p></div>
            @endforeach
          </div>

          <footer class="no-print flex items-center justify-between gap-3 border-t border-kabut-200 px-5 py-3 sm:px-7">
            @if ($i > 0)<a href="#{{ $bab[$i - 1][0] }}" class="panduan-page-link" data-bab-ke="{{ $i - 1 }}">← {{ $bab[$i - 1][1] }}</a>@else<span></span>@endif
            @if ($i < $n - 1)<a href="#{{ $bab[$i + 1][0] }}" class="panduan-page-link" data-bab-ke="{{ $i + 1 }}">{{ $bab[$i + 1][1] }} →</a>@endif
          </footer>
        </article>
      @endforeach
    </div>
  </div>

  <p class="mt-2 text-center text-xs text-kabut-500">SILANTRO · {{ \App\Models\Setting::get('nama_instansi') }} · Panduan Edisi November 2026</p>
</div>
@endsection
