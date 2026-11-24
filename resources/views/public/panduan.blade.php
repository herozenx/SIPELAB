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
    ['mengenal', 'Mengenal SILANTRO & beranda', 'beranda',
      'SILANTRO adalah layanan online Balai untuk permohonan uji mutu hasil perikanan: dari pengajuan, pembayaran SIMPONI, pengujian di laboratorium, sampai Laporan Hasil Pengujian (LHP).',
      [
        'Buka halaman depan SILANTRO. Di sana tersedia menu <b>Masuk</b>, <b>Daftar</b>, <b>Panduan</b>, dan <b>Cek keaslian LHP</b>.',
        'Setelah masuk, menu di sisi kiri berisi <b>Beranda</b>, <b>Ajukan uji</b>, <b>Permohonan saya</b>, <b>Chat petugas</b>, dan <b>Panduan</b>.',
        '<b>Beranda</b> menampilkan permohonan terbaru dan pengingat tindakan, misalnya tagihan yang perlu dibayar.',
        'Semua halaman diperbarui otomatis ketika status sampel berubah; Anda tidak perlu menekan muat ulang.',
      ],
      [['info', 'Di HP, menu dibuka lewat tombol <b>Menu</b> di kanan atas.']], null],
    ['jenis', 'Akun, pendaftaran & jenis layanan', 'akun',
      'Satu akun dipakai untuk semua permohonan instansi/usaha Anda. Permohonan online diajukan sebagai layanan Mandiri; layanan Pengujian Eksternal dan Surveilen ditetapkan petugas Balai.',
      [
        'Klik <b>Daftar</b> di halaman depan.',
        'Isi <b>nama penanggung jawab</b>, <b>nama usaha/instansi</b> (dicetak di LHP), <b>email</b>, dan <b>nomor telepon/WA</b>.',
        'Buat kata sandi minimal 8 karakter berisi huruf besar, huruf kecil, dan angka, lalu klik <b>Buat akun</b>.',
        'Untuk masuk berikutnya, klik <b>Masuk</b>, lalu isi email dan kata sandi.',
      ],
      [['penting', 'Setelah 5 kali salah kata sandi, akun dikunci 15 menit demi keamanan.'], ['info', 'Operator Balai menetapkan jenis layanan saat memverifikasi sampel.']], 'jenis'],
    ['ajukan', 'Mengajukan permohonan uji', 'tambah',
      'Permohonan diajukan online sebelum sampel dibawa ke Balai.',
      [
        'Klik menu <b>Ajukan uji</b>.',
        'Isi <b>jenis komoditas</b>, <b>asal sampel</b>, dan <b>alamat</b> (dicetak di LHP), lalu <b>nama/jenis sampel</b> dan <b>jumlah sampel</b> tiap produk. Menguji beberapa produk sekaligus? Klik <b>Tambah produk</b>; tiap produk memilih parameternya sendiri.',
        'Centang <b>parameter uji</b> yang diminta (Organoleptik, Mikrobiologi, Kimia, Radionuklida) dan atur <b>jumlah pengujian</b> tiap parameter.',
        'Pilih <b>tujuan distribusi</b>: Domestik atau Ekspor.',
        'Lampirkan dokumen pendukung bila ada (PDF/JPG/PNG, maksimal 3 berkas, masing-masing 5 MB).',
        'Periksa <b>perkiraan tagihan</b> di samping formulir, lalu klik <b>Kirim permohonan</b>.',
        'Anda langsung mendapat <b>nomor permohonan</b>, misalnya <span class="kode">PMH-221126-001</span>. Catat nomor ini.',
      ],
      [['info', 'Angka tagihan final mengikuti billing SIMPONI resmi yang diterbitkan Operator Bendahara.']], null],
    ['sampel', 'Menyerahkan sampel & verifikasi', 'lab',
      'Sampel fisik diperiksa petugas loket sebelum diuji.',
      [
        'Administrator Balai memverifikasi berkas dan data permohonan. Bila diterima, status menjadi <b>Diterima, serahkan sampel</b>.',
        'Bawa sampel ke loket penerimaan Balai dan sebutkan <b>kode permohonan</b>.',
        'Operator atau Admin Laboratorium mencocokkan sampel dengan permohonan, lalu meregistrasinya dengan <b>kode sampel</b> buku sampel, misalnya <span class="kode">SP.M/221126.01</span>. Bila ada beberapa produk, tiap produk diberi akhiran A, B, C, misalnya <span class="kode">SP.M/221126.01A</span>. <b>Bukti terima sampel</b> (PDF) bisa diunduh dari halaman permohonan.',
        '<b>Mandiri:</b> status menjadi <b>Menunggu kode billing</b>, lalu billing SIMPONI resmi muncul di halaman permohonan.',
        '<b>Pengujian Eksternal/Surveilen:</b> sampel langsung diproses; tagihan diterbitkan setelah LHP disahkan.',
        'Bila berkas/data tidak sesuai, permohonan <b>ditolak</b> beserta alasannya. Perbaiki lalu ajukan permohonan baru.',
      ],
      [], null],
    ['bayar', 'Pembayaran SIMPONI', 'berkas',
      'Layanan Mandiri membayar sebelum sampel diuji; layanan lain membayar setelah LHP disahkan.',
      [
        'Buka permohonan berstatus <b>Menunggu pembayaran</b> (juga muncul sebagai pengingat di Beranda).',
        'Lihat <b>kode billing</b> (15 digit) dan <b>jumlah setor</b>, lalu klik <b>Lihat billing resmi</b> untuk membuka dokumen billing.',
        'Bayar lewat teller bank, ATM, internet/mobile banking, atau kantor pos yang melayani SIMPONI.',
        'Unggah foto/PDF <b>bukti setor</b>, lalu klik <b>Kirim bukti pembayaran</b>. Nomor NTPN tidak perlu diketik.',
        'Operator Bendahara mencocokkan dana masuk. Setelah terkonfirmasi, sampel mulai diuji (Mandiri) atau LHP siap diambil (layanan lain).',
      ],
      [['info', 'Bila bukti ditolak, alasannya tampil di halaman permohonan dan Anda bisa mengunggah ulang.']], null],
    ['pantau', 'Memantau status permohonan', 'jam',
      'Status permohonan bisa Anda pantau kapan saja dari akun Anda.',
      [
        'Buka menu <b>Permohonan saya</b> lalu klik permohonan. Tahapannya tampil di bagian atas: Verifikasi, Pembayaran, Pengujian, Penyusunan LHP, LHP selesai.',
        'Status yang mungkin: <b>Menunggu verifikasi</b>, <b>Diterima, serahkan sampel</b>, <b>Menunggu kode billing</b>, <b>Menunggu pembayaran</b>, <b>Pembayaran sedang dicek</b>, <b>Sedang diproses/pengujian</b>, <b>Penyusunan LHP</b>, dan <b>LHP selesai</b>.',
        "Bagian <b>Perkiraan selesai</b> menunjukkan target tanggal sampel jadi ({$normaTotal} hari kerja sejak sampel mulai diuji).",
        'Halaman diperbarui sendiri setiap kali status berubah.',
      ],
      [['info', 'Hari kerja = Senin–Jumat di luar hari libur nasional dan cuti bersama.']], 'norma'],
    ['lhu', 'LHP & cek keaslian', 'perisai',
      'LHP bernomor disahkan Penanggung Jawab Laboratorium dan dilengkapi QR Code keaslian.',
      [
        'Setelah pengujian selesai, hasil divalidasi Administrator, diparaf penyelia, lalu disahkan Penanggung Jawab Laboratorium. Status berubah menjadi <b>LHP selesai</b>.',
        'Buka permohonan berstatus <b>LHP selesai</b>, lalu klik <b>LHP (PDF)</b> untuk melihat atau mengunduh LHP. Layanan yang dibayar di belakang: LHP bisa diunduh setelah pembayaran dikonfirmasi.',
        'Siapa pun (misalnya pembeli atau instansi lain) bisa mengecek keaslian LHP dengan memindai <b>QR Code</b> atau memasukkan 12 karakter <b>kode verifikasi</b> di menu <b>Cek keaslian LHP</b>.',
      ],
      [['penting', 'Jangan mengubah isi LHP. Perubahan apa pun akan terdeteksi "tidak cocok" saat dicek keasliannya.']], null],
    ['chat', 'Chat petugas & bantuan', 'chat',
      'Tanyakan apa saja langsung kepada petugas Balai lewat chat di dalam SILANTRO.',
      [
        'Klik tombol <b>Chat petugas</b> di pojok kanan bawah, atau menu <b>Chat petugas</b> di sisi kiri.',
        'Tulis pertanyaan, sebutkan <b>kode permohonan</b> bila terkait sampel tertentu, lalu tekan <b>Enter</b> atau <b>Kirim</b>.',
        'Pesan dibalas oleh petugas Balai; nama dan jabatan pembalas tampil di setiap balasan.',
        'Daftar <b>Petugas online</b> menunjukkan siapa saja yang sedang bertugas saat ini.',
        'Lencana merah di menu menandakan ada balasan yang belum Anda baca.',
      ],
      array_values(array_filter([
        ['penting', 'Jangan mengirim kata sandi atau data rahasia lewat chat.'],
        $wa ? ['info', 'Tidak ada petugas online? Hubungi juga <b>WhatsApp Center</b> Balai lewat tombol di bawah.'] : null,
      ])), 'bantuan'],
    ['faq', 'Tanya jawab & istilah', 'info',
      'Jawaban atas pertanyaan yang sering diajukan dan arti istilah di SILANTRO.',
      [], [], 'faq'],
  ];
  $n = count($bab);
  $faq = [
    ['Apa beda layanan Mandiri, Pengujian Eksternal, dan Surveilen?', 'Permohonan online selalu Mandiri: bayar di awal setelah sampel diverifikasi, lalu sampel diuji. Pengujian Eksternal dan Surveilen ditetapkan petugas Balai; sampel langsung diuji dan tagihan dibayar setelah LHP disahkan.'],
    ['Berapa lama sampai LHP selesai?', "Norma waktu pelayanan {$normaTotal} hari kerja sejak sampel mulai diuji. Target tanggal selesai tampil di halaman permohonan Anda."],
    ['Kenapa hasil uji belum tampil di akun saya?', 'Hasil uji dan LHP baru bisa dibuka setelah LHP disahkan dan permohonan selesai (termasuk lunas untuk layanan yang dibayar di belakang), supaya yang Anda terima adalah hasil final.'],
    ['Apakah saya perlu mengetik NTPN?', 'Tidak. Cukup unggah bukti setor SIMPONI. Operator Bendahara mencocokkan pembayaran dari bukti dan sistem SIMPONI.'],
    ['Format berkas apa yang bisa diunggah?', 'PDF, JPG, atau PNG asli, maksimal 5 MB per berkas. Berkas yang hanya diganti ekstensinya akan ditolak.'],
    ['Lupa kata sandi?', 'Hubungi petugas lewat chat atau WhatsApp Center, atau datang ke loket. Petugas akan mereset sandi; Anda wajib menggantinya saat masuk.'],
  ];
  $istilah = [
    ['SIMPONI', 'Sistem Informasi PNBP Online milik Kementerian Keuangan untuk menyetor tagihan layanan.'],
    ['Kode billing', '15 digit kode tagihan SIMPONI yang dipakai saat membayar.'],
    ['NTPN', 'Nomor Transaksi Penerimaan Negara di bukti setor, tanda pembayaran sah.'],
    ['PNBP', 'Penerimaan Negara Bukan Pajak; tarif layanan uji termasuk PNBP.'],
    ['Kode sampel', 'Kode registrasi dari buku sampel, mis. SP.M/221126.01 (jenis layanan / tanggal diterima . nomor urut harian; akhiran A, B, C untuk tiap produk bila lebih dari satu).'],
    ['LHP', 'Laporan Hasil Pengujian, dokumen resmi hasil pengujian sampel.'],
    ['Norma waktu', 'Batas waktu pelayanan dalam hari kerja, per lab maupun total.'],
  ];
  $catatanGaya = ['info' => ['bg-laut-50 text-laut-700', 'info', 'Info'], 'penting' => ['bg-surya-50 text-surya-700', 'info', 'Penting']];
@endphp

<div class="{{ $masuk ? '' : 'mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12' }}">
  {{-- Kepala panduan --}}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-xs font-bold uppercase tracking-wider text-laut-600">Panduan resmi SILANTRO</p>
      <h1 class="mt-1">Panduan Penggunaan SILANTRO</h1>
      <p class="mt-2 max-w-prose text-kabut-600">Langkah demi langkah untuk pengguna jasa: akun, permohonan, pembayaran, pemantauan, sampai LHP.</p>
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
                  <a href="{{ route('verifikasi') }}" class="btn-ghost"><x-ikon n="perisai" class="h-4 w-4" />Cek keaslian LHP</a>
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

  <p class="mt-2 text-center text-xs text-kabut-500">SILANTRO · {{ \App\Models\Setting::get('nama_instansi') }} · Panduan Edisi November 2026</p>
</div>
@endsection
