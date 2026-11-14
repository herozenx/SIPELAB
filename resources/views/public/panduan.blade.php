@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Panduan penggunaan')
@section('content')
@php
  $masuk = auth()->check();
  $wa = wa_link('Halo Balai, saya ingin bertanya tentang layanan uji SIPELAB.');
  $labNama = $labs->pluck('nama')->join(' → ');
  /*
   * Setiap bab: id, judul, ikon, pengantar, langkah (boleh <b>), catatan [jenis, teks], khusus (blok tambahan).
   * Teks ditulis tim Balai; ubah di sini untuk memperbarui panduan.
   */
  $bab = [
    ['mengenal', 'Mengenal SIPELAB & beranda', 'beranda',
      'SIPELAB adalah layanan online Balai untuk permohonan uji mutu hasil perikanan: dari pengajuan, pembayaran SIMPONI, pengujian di laboratorium, sampai Laporan Hasil Uji (LHU) digital.',
      [
        'Buka halaman depan SIPELAB. Di sana tersedia menu <b>Masuk</b>, <b>Daftar</b>, <b>Panduan</b>, dan <b>Cek keaslian LHU</b>.',
        'Setelah masuk, menu di sisi kiri berisi <b>Beranda</b>, <b>Ajukan uji</b>, <b>Permohonan saya</b>, <b>Chat petugas</b>, dan <b>Panduan</b>.',
        '<b>Beranda</b> menampilkan permohonan terbaru dan pengingat tindakan, misalnya tagihan yang perlu dibayar.',
        'Semua halaman diperbarui otomatis ketika status sampel berubah; Anda tidak perlu menekan muat ulang.',
      ],
      [['info', 'Di HP, menu dibuka lewat tombol <b>Menu</b> di kanan atas.']], null],
    ['jenis', 'Akun, pendaftaran & jenis pengguna jasa', 'akun',
      'Satu akun dipakai untuk semua permohonan instansi/usaha Anda. Saat mendaftar Anda memilih jenis pengguna jasa yang menentukan kapan membayar.',
      [
        'Klik <b>Daftar</b> di halaman depan.',
        'Isi <b>nama penanggung jawab</b>, <b>nama usaha/instansi</b> (dicetak di LHU), <b>email</b>, dan <b>nomor telepon/WA</b>.',
        'Pilih <b>jenis pengguna jasa</b>: <b>Mandiri</b> atau <b>Eksternal</b> (lihat perbandingan di bawah).',
        'Buat kata sandi minimal 8 karakter berisi huruf besar, huruf kecil, dan angka, lalu klik <b>Buat akun</b>.',
        'Untuk masuk berikutnya, klik <b>Masuk</b>, pilih <b>Masuk sebagai: Pengguna jasa</b>, isi email dan kata sandi.',
      ],
      [['penting', 'Setelah 5 kali salah kata sandi, akun dikunci 15 menit demi keamanan.'], ['info', 'Tidak yakin memilih? Pilih Mandiri. Admin Lab dapat menyesuaikan jenis saat verifikasi sampel.']], 'jenis'],
    ['ajukan', 'Mengajukan permohonan uji', 'tambah',
      'Permohonan diajukan online sebelum sampel dibawa ke Balai.',
      [
        'Klik menu <b>Ajukan uji</b>.',
        'Isi <b>nama sampel</b>, <b>jenis komoditas</b>, <b>jumlah sampel</b> (1–500), dan <b>asal sampel</b>.',
        'Pilih <b>tujuan distribusi</b>: Domestik, Ekspor, atau Impor. Tujuan menentukan kalimat keputusan di LHU.',
        'Lampirkan dokumen pendukung bila ada (PDF/JPG/PNG, maksimal 3 berkas, masing-masing 5 MB).',
        'Periksa <b>perkiraan tagihan</b> di samping formulir, lalu klik <b>Kirim permohonan</b>.',
        'Anda mendapat kode permohonan, misalnya <span class="kode">PMH-2026-00001</span>. Catat kode ini.',
      ],
      [['info', 'Angka tagihan final ditetapkan Admin Lab saat verifikasi sampel.']], null],
    ['sampel', 'Menyerahkan sampel & verifikasi', 'lab',
      'Sampel fisik diperiksa petugas loket sebelum diuji.',
      [
        'Bawa sampel ke loket penerimaan Balai dan sebutkan <b>kode permohonan</b>.',
        'Admin Lab mencocokkan jenis, jumlah, dan kondisi sampel dengan data permohonan.',
        '<b>Mandiri:</b> bila sesuai, kode billing SIMPONI dan tagihan muncul di halaman permohonan Anda.',
        '<b>Eksternal:</b> bila sesuai, sampel langsung diproses tanpa menunggu pembayaran.',
        'Bila tidak sesuai, permohonan <b>ditolak</b> beserta alasannya. Perbaiki lalu ajukan permohonan baru.',
      ],
      [], null],
    ['bayar', 'Pembayaran SIMPONI (Mandiri)', 'berkas',
      'Pengguna jasa Mandiri membayar sebelum sampel diuji.',
      [
        'Buka permohonan berstatus <b>Menunggu pembayaran</b> (juga muncul sebagai pengingat di Beranda).',
        'Salin <b>kode billing</b> (15 digit) dan <b>jumlah setor</b>.',
        'Bayar lewat teller bank, ATM, internet/mobile banking, atau kantor pos yang melayani SIMPONI.',
        'Isi <b>NTPN</b> (16 huruf/angka) persis seperti di bukti setor, unggah foto/PDF bukti setor, lalu klik <b>Kirim bukti pembayaran</b>.',
        'Admin Lab mencocokkan dana masuk. Setelah terkonfirmasi, sampel mulai diproses di laboratorium.',
      ],
      [['penting', 'Satu NTPN hanya berlaku untuk satu permohonan.'], ['info', 'Bila bukti ditolak, alasannya tampil di halaman permohonan dan Anda bisa mengunggah ulang.']], null],
    ['eksternal', 'Eksternal: pelunasan & pengambilan LHU', 'kunci',
      'Pengguna jasa Eksternal membayar setelah LHU jadi. LHU baru bisa diambil setelah lunas.',
      [
        'Setelah pengujian selesai dan LHU divalidasi, status berubah menjadi <b>LHU jadi, menunggu pelunasan</b>.',
        'Buka permohonan: tampil <b>nomor LHU</b>, <b>kode billing</b> pelunasan, dan <b>jumlah setor</b>.',
        'Bayar lewat SIMPONI, lalu isi <b>NTPN</b> dan unggah bukti setor seperti pada pembayaran Mandiri.',
        'Setelah Admin Lab mengonfirmasi dana masuk, status menjadi <b>LHU terbit</b> dan LHU bisa dilihat serta diunduh.',
      ],
      [['penting', 'Sebelum lunas, LHU dan nilai hasil uji belum bisa dibuka atau diunduh.']], null],
    ['pantau', 'Memantau permohonan & norma waktu', 'jam',
      'Status permohonan bisa Anda pantau kapan saja dari akun Anda.',
      [
        'Buka menu <b>Permohonan saya</b> lalu klik permohonan.',
        'Selama sampel diuji, status menampilkan <b>Sedang diproses</b>.',
        "Bagian <b>Perkiraan selesai</b> menunjukkan target tanggal sampel jadi ({$normaTotal} hari kerja sejak sampel mulai diproses).",
        'Halaman diperbarui sendiri. Begitu LHU terbit (Eksternal: begitu tagihan pelunasan terbit), statusnya langsung berubah.',
        'Nilai hasil uji ditampilkan setelah LHU divalidasi.',
      ],
      [['info', 'Hari kerja = Senin–Jumat di luar hari libur nasional dan cuti bersama.']], 'norma'],
    ['lhu', 'LHU & cek keaslian', 'perisai',
      'LHU diterbitkan digital, bernomor, dan dilengkapi kode verifikasi.',
      [
        'Buka permohonan berstatus <b>LHU terbit</b>, lalu klik <b>Lihat dan cetak LHU</b>.',
        'Klik <b>Cetak atau simpan PDF</b> untuk menyimpan LHU.',
        'Siapa pun (misalnya pembeli atau instansi lain) bisa mengecek keaslian LHU di menu <b>Cek keaslian LHU</b> dengan 12 karakter kode verifikasi yang tercetak.',
        'Keputusan: semua hasil <b>Negatif</b> → <b>Lulus uji</b>; ada hasil <b>Positif</b> → <b>Tidak lulus uji</b>.',
      ],
      [['penting', 'Jangan mengubah isi LHU. Perubahan apa pun akan terdeteksi "tidak cocok" saat dicek keasliannya.']], null],
    ['chat', 'Chat petugas & bantuan', 'chat',
      'Tanyakan apa saja langsung kepada petugas Balai lewat chat di dalam SIPELAB.',
      [
        'Klik tombol <b>Chat petugas</b> di pojok kanan bawah, atau menu <b>Chat petugas</b> di sisi kiri.',
        'Tulis pertanyaan, sebutkan <b>kode permohonan</b> bila terkait sampel tertentu, lalu tekan <b>Enter</b> atau <b>Kirim</b>.',
        'Pesan dibalas oleh admin atau operator lab; nama dan jabatan pembalas tampil di setiap balasan.',
        'Daftar <b>Petugas online</b> menunjukkan siapa saja yang sedang bertugas saat ini.',
        'Lencana merah di menu menandakan ada balasan yang belum Anda baca.',
      ],
      array_values(array_filter([
        ['penting', 'Jangan mengirim kata sandi atau data rahasia lewat chat.'],
        $wa ? ['info', 'Tidak ada petugas online? Hubungi juga <b>WhatsApp Center</b> Balai lewat tombol di bawah.'] : null,
      ])), 'bantuan'],
    ['faq', 'Tanya jawab & istilah', 'info',
      'Jawaban atas pertanyaan yang sering diajukan dan arti istilah di SIPELAB.',
      [], [], 'faq'],
  ];
  $n = count($bab);
  $faq = [
    ['Apa beda pengguna jasa Mandiri dan Eksternal?', 'Mandiri membayar di awal: setelah sampel diverifikasi, bayar dulu, lalu sampel diuji. Eksternal tidak perlu bayar di awal: sampel langsung diuji, tagihan dibayar setelah LHU jadi, dan LHU baru bisa dilihat/diunduh setelah pembayaran dikonfirmasi.'],
    ['Berapa lama sampai LHU jadi?', "Norma waktu pelayanan {$normaTotal} hari kerja sejak sampel masuk laboratorium. Target tanggal selesai tampil di halaman permohonan Anda."],
    ['Kenapa nilai hasil uji belum terlihat?', 'Nilai hasil tiap lab ditampilkan setelah LHU divalidasi Admin Lab (Eksternal: setelah lunas), supaya yang Anda terima adalah hasil final.'],
    ['NTPN saya ditolak, apa yang harus dilakukan?', 'Pastikan NTPN disalin persis (16 huruf/angka, tanpa spasi) dan belum pernah dipakai permohonan lain. Bila bukti ditolak admin, alasannya tampil di halaman permohonan dan Anda bisa mengunggah ulang.'],
    ['Format berkas apa yang bisa diunggah?', 'PDF, JPG, atau PNG asli, maksimal 5 MB per berkas. Berkas yang hanya diganti ekstensinya akan ditolak.'],
    ['Lupa kata sandi?', 'Hubungi petugas lewat chat atau WhatsApp Center, atau datang ke loket. Admin akan mereset sandi; Anda wajib menggantinya saat masuk.'],
  ];
  $istilah = [
    ['SIMPONI', 'Sistem Informasi PNBP Online milik Kementerian Keuangan untuk menyetor tagihan layanan.'],
    ['Kode billing', '15 digit kode tagihan SIMPONI yang dipakai saat membayar.'],
    ['NTPN', 'Nomor Transaksi Penerimaan Negara, 16 huruf/angka di bukti setor, tanda pembayaran sah.'],
    ['PNBP', 'Penerimaan Negara Bukan Pajak; tarif layanan uji termasuk PNBP.'],
    ['LHU', 'Laporan Hasil Uji, dokumen resmi hasil pengujian sampel.'],
    ['Norma waktu', 'Batas waktu pelayanan dalam hari kerja, per lab maupun total.'],
  ];
  $catatanGaya = ['info' => ['bg-laut-50 text-laut-700', 'info', 'Info'], 'penting' => ['bg-surya-50 text-surya-700', 'info', 'Penting']];
@endphp

<div class="{{ $masuk ? '' : 'mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12' }}">
  {{-- Kepala panduan --}}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-bold uppercase tracking-wider text-laut-600">Panduan resmi SIPELAB</p>
      <h1 class="mt-1">Panduan Penggunaan SIPELAB</h1>
      <p class="mt-2 max-w-prose text-kabut-600">Langkah demi langkah untuk pengguna jasa: akun, permohonan, pembayaran, pemantauan, sampai LHU.</p>
      <p class="mt-2 text-xs text-kabut-500">Edisi November 2026 · {{ $n }} bagian</p>
    </div>
    <div class="no-print flex flex-wrap gap-2">
      <a href="{{ $masuk ? route('dashboard') : route('home') }}" class="btn-ghost"><x-ikon n="kembali" class="h-4 w-4" />{{ $masuk ? 'Beranda' : 'Kembali ke portal' }}</a>
      <button type="button" class="btn-primary" data-print><x-ikon n="unggah" class="h-4 w-4 rotate-180" />Unduh PDF</button>
    </div>
  </div>

  <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[270px_minmax(0,1fr)]">
    {{-- Daftar bagian --}}
    <nav class="no-print panel self-start lg:sticky lg:top-24" aria-label="Daftar bagian panduan">
      <div class="panel-head"><h2 class="text-base">Daftar bagian</h2><span class="num text-xs text-kabut-500" data-bab-pos>1 / {{ $n }}</span></div>
      <ol class="p-2">
        @foreach ($bab as $i => [$id, $judul])
          <li><a href="#{{ $id }}" data-bab-link class="flex items-start gap-3 rounded-lg px-3 py-2 text-sm text-tinta no-underline hover:bg-kabut-50 hover:no-underline aria-[current=true]:bg-laut-50 aria-[current=true]:font-semibold aria-[current=true]:text-laut-700"><span class="num w-6 shrink-0 text-xs font-bold text-kabut-400">{{ sprintf('%02d', $i + 1) }}</span><span>{{ $judul }}</span></a></li>
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
                <div class="grid gap-4 md:grid-cols-2">
                  @foreach ([
                    ['Mandiri', 'Bayar di awal', ['Ajukan permohonan', 'Serahkan sampel, diverifikasi', 'Bayar tagihan SIMPONI', 'Sampel diuji di lab', 'LHU terbit, langsung bisa diunduh'], 'bg-laut-600'],
                    ['Eksternal', 'Bayar saat LHU jadi', ['Ajukan permohonan', 'Serahkan sampel, diverifikasi', 'Sampel langsung diuji di lab', 'LHU jadi, kode billing dikirim', 'Bayar, lalu LHU bisa diambil'], 'bg-lamun-600'],
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
                      <tr><td>Tarif pengujian per sampel</td><td class="num text-right">{{ rupiah($labs->sum('tarif')) }}</td></tr>
                      <tr><td class="text-kabut-500">Biaya administrasi per permohonan</td><td class="num text-right text-kabut-500">{{ rupiah($biayaAdmin) }}</td></tr>
                    </tbody>
                  </table>
                </div>
                @break
              @case('bantuan')
                <div class="flex flex-wrap gap-2">
                  <a href="{{ $masuk ? route('chat') : route('login') }}" class="btn-primary"><x-ikon n="chat" class="h-4 w-4" />Chat petugas</a>
                  @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="btn-ghost"><x-ikon n="chat" class="h-4 w-4" />Chat WhatsApp Center</a>@endif
                  <a href="{{ route('verifikasi') }}" class="btn-ghost"><x-ikon n="perisai" class="h-4 w-4" />Cek keaslian LHU</a>
                </div>
                @break
              @case('faq')
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
            @if ($i > 0)<a href="#{{ $bab[$i - 1][0] }}" class="text-sm font-semibold no-underline" data-bab-ke="{{ $i - 1 }}">← {{ $bab[$i - 1][1] }}</a>@else<span></span>@endif
            @if ($i < $n - 1)<a href="#{{ $bab[$i + 1][0] }}" class="text-right text-sm font-semibold no-underline" data-bab-ke="{{ $i + 1 }}">{{ $bab[$i + 1][1] }} →</a>@endif
          </footer>
        </article>
      @endforeach
    </div>
  </div>

  <p class="mt-2 text-center text-xs text-kabut-500">SIPELAB · {{ \App\Models\Setting::get('nama_instansi') }} · Panduan Edisi November 2026</p>
</div>
@endsection
