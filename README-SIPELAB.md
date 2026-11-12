# SIPELAB — Panduan Pemakaian & Pengubahan

**Sistem Pelayanan, Pengujian & Sertifikasi Laboratorium** — BPPMHKP Surabaya II
Laravel 11 · PHP 8.3 · SQLite (laptop) · Neon Postgres (demo Vercel) · MySQL/MariaDB (server instansi)

> Cara membaca file ini dengan rapi: buka di **VS Code** lalu tekan `Ctrl+Shift+V` (preview).

**Lokasi proyek di laptop:** `C:\laragon\www\sipelab`
Semua path file di panduan ini **relatif terhadap folder tersebut**.

---

## Daftar isi

1. [Menjalankan di laptop](#1-menjalankan-di-laptop)
2. [Akun & peran](#2-akun--peran)
3. [Alur permohonan dari awal sampai LHU](#3-alur-permohonan-dari-awal-sampai-lhu)
4. [Tutorial per peran](#4-tutorial-per-peran)
5. [Mengubah sesuatu — buka file apa?](#5-mengubah-sesuatu--buka-file-apa)
6. [Setelah mengubah file, jalankan apa?](#6-setelah-mengubah-file-jalankan-apa)
7. [Hosting demo di Vercel](#7-hosting-demo-di-vercel-gratis)
8. [Masalah umum](#8-masalah-umum)

---

## 1. Menjalankan di laptop

### Cara paling mudah
Klik dua kali **`C:\laragon\www\sipelab\demo.bat`**

| Pilihan | Fungsi |
|---|---|
| `1` | Jalankan dengan data yang sudah ada |
| `2` | **Reset** ke data demo bersih, lalu jalankan (pakai sebelum presentasi) |

Lalu buka:
- Di laptop ini: **http://localhost:8000**
- Dari HP/laptop lain di Wi-Fi yang sama: **http://IP-LAPTOP:8000** (IP ditampilkan di jendela demo, mis. `192.168.1.3`)

Pertama kali, Windows Firewall bertanya soal `php.exe` → centang **Private networks** → **Allow**.
Jendela hitam **jangan ditutup** selama aplikasi dipakai. Tutup jendela = aplikasi berhenti.

### Cara manual (terminal)
```powershell
cd C:\laragon\www\sipelab
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
& $php artisan serve                    # jalankan
& $php artisan migrate:fresh --seed     # reset data demo
```
> Di **Terminal Laragon** cukup ketik `php artisan ...`.

---

## 2. Akun & peran

Akun demo (hanya ada di laptop/demo), sandi semua: **`Lab12345!`**

| Peran | Pilih "Masuk sebagai" | Email | Bisa apa |
|---|---|---|---|
| **Superadmin** | Superadmin | `superadmin@lab.local` | Semua fitur Admin Lab + kelola pengguna, lab & tarif, pengaturan, log aktivitas |
| **Admin Lab** | Operator | `admin@lab.local` | Verifikasi sampel, beri kode billing, konfirmasi bayar, validasi & terbitkan LHU, laporan |
| **Petugas Nekropsi** | Operator | `nekropsi@lab.local` | Input hasil uji sampel yang sedang di lab Nekropsi |
| **Petugas Mikrobiologi** | Operator | `mikro@lab.local`, `mikro2@lab.local` | Input hasil di lab Mikrobiologi (2 operator dalam satu tim) |
| **Petugas Kimia** | Operator | `kimia@lab.local` | Input hasil di lab Kimia |
| **Pengguna Jasa Mandiri** | Pengguna jasa | `pengguna@lab.local` | **Bayar di awal**, lalu sampel diuji |
| **Pengguna Jasa Eksternal** | Pengguna jasa | `eksternal@lab.local` | Sampel langsung diuji, **bayar setelah LHU jadi**; LHU terkunci sampai lunas |

Perbedaan tampilan:
- **Admin Lab & Superadmin** → beranda berisi antrean tindakan (verifikasi, cek bayar, validasi LHU), jumlah sampel per lab, dan daftar **sampel yang lewat norma waktu**. Menu tambahan **Tim operator** dan **Chat pengguna**.
- **Petugas (operator)** → hanya antrean sampel di lab-nya sendiri, diurutkan dari batas norma terdekat; tombol **Tangani sampel**; menu **Chat pengguna**. Tidak melihat tagihan, hasil lab lain, maupun LHU.
- **Pengguna jasa** → hanya permohonan miliknya; saat sampel diuji cukup melihat status **Sedang diproses** + perkiraan selesai (tanpa jalur lab, nama operator, atau riwayat antar-lab); nilai hasil uji baru tampil setelah LHU tervalidasi (Eksternal: setelah lunas). Menu **Chat petugas** + tombol chat melayang, dan **Panduan**.

Keamanan akun: 5x salah sandi → akun **terkunci 15 menit** (Superadmin bisa buka lewat menu Pengguna → *Buka kunci*). Sesi berakhir setelah **30 menit tidak aktif** (maks. 10 jam).

---

## 3. Alur permohonan dari awal sampai LHU

```
Pengguna ajukan ──▶ [Menunggu verifikasi]
                        │ Admin: sampel sesuai + kode billing      (atau tolak + alasan ──▶ [Ditolak])
                        ▼
                    [Menunggu pembayaran]
                        │ Pengguna: NTPN + bukti setor
                        ▼
                    [Cek pembayaran] ── Admin: bukti ditolak ──▶ kembali ke Menunggu pembayaran
                        │ Admin: dana masuk
                        ▼
                    [Sedang diuji]  Nekropsi ──▶ Mikrobiologi ──▶ Kimia   (diteruskan otomatis)
                        ▼
                    [Menunggu validasi LHU]
                        │ Admin: validasi & terbitkan
                        ▼
                    [LHU terbit]  ──▶ bisa dicek keasliannya oleh publik
```

Alur di atas untuk pengguna jasa **Mandiri**. Untuk pengguna jasa **Eksternal**:

```
Pengguna ajukan ──▶ [Menunggu verifikasi]
                        │ Admin: sampel sesuai (tanpa kode billing)
                        ▼
                    [Sedang diuji]  Nekropsi ──▶ Mikrobiologi ──▶ Kimia
                        ▼
                    [Menunggu validasi LHU]
                        │ Admin: terbitkan LHU + isi kode billing pelunasan
                        ▼
                    [LHU jadi, menunggu pelunasan]   ← LHU BELUM bisa dibuka pengguna
                        │ Pengguna: NTPN + bukti setor → Admin: dana masuk
                        ▼
                    [LHU terbit]  ──▶ pengguna bisa melihat & mengunduh LHU
```

- Jenis (Mandiri/Eksternal) dipilih pengguna saat **Daftar**; Admin bisa mengubahnya saat **verifikasi** sampel (pilihan *Skema pembayaran*), Superadmin juga di menu **Pengguna**.
- Semua hasil **Negatif** → "Lulus uji, bisa beredar / ekspor / impor" (sesuai tujuan).
- Ada satu saja **Positif** → "Tidak lulus uji".
- **Tagihan** = (jumlah tarif semua lab aktif × jumlah sampel) + biaya administrasi.
- Halaman memperbarui diri otomatis tiap ±8 detik jika ada perubahan data.

### Norma waktu (6 hari kerja)
- Target sampel jadi **6 hari kerja** sejak masuk lab pertama (diatur di **Pengaturan**).
- Tiap lab punya norma sendiri (bawaan **Nekropsi 1, Mikrobiologi 3, Kimia 2** hari kerja; diatur di **Lab & tarif**).
- Hari kerja = Senin–Jumat, **di luar tanggal libur** yang diisi di **Pengaturan → Hari libur**.
- Status tampil sebagai lencana: *Sisa N hari kerja*, *Batas hari ini*, *Lewat N hari kerja*, *Selesai, tepat waktu*, *Terlambat N hari kerja*.
- Terlihat di: halaman detail permohonan (tabel **Tim penanganan & norma waktu**), antrean petugas, beranda admin (**Sampel melewati norma waktu**).

---

## 4. Tutorial per peran

### 4.1 Pengguna Jasa
1. **Daftar** di halaman depan → *Daftar* (atau masuk dengan akun yang ada).
2. Menu **Ajukan uji** → isi:
   - Nama sampel, jenis komoditas, jumlah sampel (1–500), asal sampel
   - Tujuan: **Domestik / Ekspor / Impor**
   - Keterangan & lampiran (opsional; PDF/JPG/PNG, maks. 5 MB, maks. 3 berkas)
3. Tunggu verifikasi.
   - **Mandiri**: setelah disetujui muncul **kode billing** dan **total tagihan** → bayar dulu.
   - **Eksternal**: sampel langsung diuji; kode billing muncul **setelah LHU jadi**.
4. Bayar lewat SIMPONI, lalu isi **NTPN** (16 huruf/angka) dan unggah **bukti setor** (PDF/JPG/PNG, maks. 5 MB).
5. Pantau status di **Permohonan saya**: selama diuji tampil **Sedang diproses** beserta **perkiraan selesai** (norma waktu). Tahapan internal lab (lab mana, operator siapa, perpindahan antar-lab) **tidak** ditampilkan ke pengguna jasa — hanya petugas Balai yang melihatnya.
6. Setelah status **LHU terbit** → buka permohonan → **Lihat/Cetak LHU** (Eksternal: setelah pelunasan dikonfirmasi).
7. Ada pertanyaan? Klik **Chat petugas** (pojok kanan bawah). Terlihat juga siapa saja petugas yang sedang online.
8. Bingung? Menu **Panduan**: buku panduan 10 bagian dengan tombol ← → dan **Unduh PDF** (juga bisa dibuka tanpa login di `/panduan`, dan lewat **Pusat bantuan** di halaman depan).

### 4.2 Admin Lab
1. **Beranda** menampilkan 3 antrean: *Menunggu verifikasi*, *Cek pembayaran*, *Menunggu validasi LHU*.
2. **Verifikasi**: buka permohonan → periksa data sampel → pilih **Skema pembayaran** →
   - Sesuai + **Mandiri**: isi **kode billing 15 digit** → kirim.
   - Sesuai + **Eksternal**: tanpa kode billing → sampel langsung masuk lab pertama.
   - Tidak sesuai: **Tolak** + tulis alasan.
3. **Cek pembayaran**: buka bukti setor → cocokkan NTPN dengan SIMPONI →
   - **Dana masuk** → Mandiri: sampel masuk lab pertama; Eksternal: LHU terbuka untuk pengguna.
   - **Tolak bukti** + alasan → pengguna diminta mengunggah ulang.
4. **Validasi LHU**: setelah semua lab selesai → periksa rekap → **Terbitkan LHU** (Eksternal: isi juga kode billing pelunasan).
5. **Tim operator**: tambah operator ke tiap tim lab (sandi sementara dibuat otomatis, tampil sekali), pindah tim, nonaktifkan, reset sandi, buka kunci. Terlihat siapa yang **online** dan berapa sampel yang sedang ditangani tiap operator.
6. **Chat pengguna**: kotak masuk semua percakapan pengguna jasa; balas langsung (nama & jabatan Anda tampil).
7. Menu **Semua permohonan** (cari/filter) dan **Laporan** (statistik, PNBP, rata-rata durasi, per lab, per bulan).

### 4.3 Petugas Lab (Nekropsi / Mikrobiologi / Kimia)
1. **Beranda** → antrean sampel di lab Anda, urut dari **batas norma terdekat**, lengkap dengan siapa yang sudah menangani.
2. Buka sampel → klik **Tangani sampel** agar nama Anda tercatat (beberapa operator boleh menangani sampel yang sama).
3. Pilih hasil **Negatif / Positif** → catatan (opsional) → simpan. Penginput hasil otomatis tercatat sebagai penangan.
4. Sampel otomatis diteruskan ke lab berikutnya (atau ke Admin untuk validasi bila lab terakhir).
5. Menu **Riwayat uji** → daftar hasil yang pernah diinput di lab Anda.
6. Menu **Chat pengguna** → ikut membalas pertanyaan pengguna jasa.

### 4.4 Superadmin
| Menu | Fungsi |
|---|---|
| **Pengguna** | Tambah akun Admin/Petugas (pilih lab untuk petugas), ubah peran, **jenis pengguna jasa (Mandiri/Eksternal)**, nonaktifkan, **reset sandi** (sandi sementara wajib diganti saat masuk), **buka kunci** |
| **Tim operator** | Sama seperti Admin Lab: kelola operator per tim lab |
| **Lab & tarif** | Tambah/ubah lab, **urutan** pengujian, **tarif** per sampel, **norma waktu (hari kerja)**, aktif/nonaktif |
| **Pengaturan** | Nama instansi, alamat instansi (tampil di kop LHU), **biaya administrasi**, **prefiks nomor LHU**, **target norma total (6 hari kerja)**, **daftar hari libur** |
| **Log aktivitas** | Jejak semua aksi penting (login, verifikasi, pembayaran, input hasil, validasi, unduh dokumen) + IP |

### 4.5 Publik (tanpa login)
- Halaman depan: alur layanan & tarif.
- **Cek keaslian LHU**: masukkan kode verifikasi yang tercetak di LHU → tampil "asli/cocok" atau "tidak cocok".

---

## 5. Mengubah sesuatu — buka file apa?

### 5.1 Lewat aplikasi (TANPA edit file) — login sebagai Superadmin
| Yang ingin diubah | Menu |
|---|---|
| Nama & alamat instansi (kop LHU) | Pengaturan |
| Biaya administrasi | Pengaturan |
| Prefiks nomor LHU (mis. `LHU/BPPMHKP-SBY2`) | Pengaturan |
| Daftar lab, urutan uji, tarif per sampel, **norma hari kerja per lab** | Lab & tarif |
| **Target norma total** (6 hari kerja) & **hari libur** | Pengaturan |
| **Nomor WhatsApp Center** (tombol Chat WhatsApp di Pusat bantuan, Panduan, Chat) | Pengaturan |
| Akun petugas/admin, reset sandi, **jenis pengguna jasa** | Pengguna |
| **Operator tiap tim lab** (juga bisa oleh Admin Lab) | Tim operator |

> Utamakan cara ini. Perubahan langsung berlaku, tercatat di log, dan tidak perlu menyentuh kode.

### 5.2 Konfigurasi (file `.env` dan `config/`)
| Yang ingin diubah | File | Baris / kunci |
|---|---|---|
| Database (SQLite ↔ MySQL/MariaDB) | `.env` | `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Alamat aplikasi | `.env` | `APP_URL` |
| Tampilkan/sembunyikan detail error | `.env` | `APP_DEBUG` (`true` saat mengembangkan, `false` saat demo/production) |
| Lama sesi tidak aktif (menit) | `.env` | `SESSION_LIFETIME` (bawaan 30) |
| Batas maksimal sesi sejak login (menit) | `.env` | `SIPELAB_SESSION_ABSOLUTE` (bawaan 600 = 10 jam) |
| Interval cek pembaruan otomatis (detik) | `.env` | `SIPELAB_POLL_SECONDS` (bawaan 8) |
| Buat akun demo saat seed | `.env` | `SEED_DEMO`, `SEED_PASSWORD` |
| **Format kode billing** (bawaan 15 digit) | `config/sipelab.php` | `regex_kode_billing` |
| **Format NTPN** (bawaan 16 huruf besar/angka) | `config/sipelab.php` | `regex_ntpn` |
| Jumlah gagal login sebelum dikunci / lama kunci | `config/sipelab.php` | `lockout_attempts`, `lockout_minutes` |
| Nilai bawaan pengaturan saat instal baru | `app/Models/Setting.php` | `DEFAULTS` |
| Lab, tarif & norma bawaan saat instal baru | `database/seeders/DatabaseSeeder.php` | baris `['Nekropsi', 1, 75000, 1]` = [nama, urutan, tarif, norma hari kerja] |
| Akun demo (nama, email) | `database/seeders/DatabaseSeeder.php` | array `$akun` |

### 5.3 Tulisan & tampilan halaman (`resources/views/`)
| Halaman | File |
|---|---|
| Halaman depan publik (judul, langkah, tarif) | `resources/views/public/home.blade.php` |
| Halaman cek keaslian LHU | `resources/views/public/verifikasi.blade.php` |
| **Dokumen LHU** (kop, isi, tanda tangan, layout cetak) | `resources/views/permohonan/lhu.blade.php` |
| Form ajukan uji | `resources/views/permohonan/create.blade.php` |
| Detail permohonan (tombol aksi admin/petugas/pengguna) | `resources/views/permohonan/show.blade.php` |
| Daftar permohonan | `resources/views/permohonan/index.blade.php` + `resources/views/partials/tabel-permohonan.blade.php` |
| Beranda Admin / Petugas / Pengguna | `resources/views/dashboard/admin.blade.php`, `petugas.blade.php`, `pengguna.blade.php` |
| Laporan | `resources/views/dashboard/laporan.blade.php` |
| Riwayat uji petugas | `resources/views/dashboard/riwayat.blade.php` |
| Halaman login / daftar / akun saya | `resources/views/auth/login.blade.php`, `register.blade.php`, `akun.blade.php` |
| **Menu samping** (isi menu per peran) & kerangka halaman | `resources/views/layouts/app.blade.php` (bagian `@php` paling atas) |
| Kerangka halaman publik/login | `resources/views/layouts/guest.blade.php` |
| Halaman Superadmin | `resources/views/admin/users.blade.php`, `labs.blade.php`, `settings.blade.php`, `audit.blade.php` |
| **Tim operator** (kelola operator per lab) | `resources/views/admin/tim.blade.php` |
| **Chat** (pengguna & kotak masuk petugas) | `resources/views/chat/index.blade.php` |
| **Panduan pengguna jasa** (gaya buku panduan: 10 bagian, ← →, Unduh PDF) | `resources/views/public/panduan.blade.php` (array `$bab`, `$faq`, `$istilah` di atas; ubah juga tulisan "Edisi") |
| **Pusat bantuan** di halaman depan | `resources/views/public/home.blade.php` (bagian `id="bantuan"`) |
| Tombol chat melayang & lencana pesan | `resources/views/layouts/app.blade.php` |
| Label status di badge (mis. "LHU jadi, menunggu pelunasan") | `resources/views/components/status.blade.php` |
| Halaman error (404, 403, 419, 500, dll.) | `resources/views/errors/*.blade.php` |
| Pesan sukses/gagal (kotak notifikasi) | `resources/views/partials/flash.blade.php` |
| Ikon | `resources/views/components/ikon.blade.php` |
| Logo / favicon | `public/favicon.svg` |

### 5.4 Label, istilah & pesan
| Yang ingin diubah | File |
|---|---|
| Nama status ("Menunggu verifikasi", "LHU terbit", dll.) & warnanya | `app/Enums/Status.php` → `label()`, `tone()` |
| Nama peran ("Admin Lab", "Petugas Lab", …) | `app/Enums/Role.php` → `label()` |
| Pilihan tujuan (Domestik/Ekspor/Impor) | `app/Enums/Tujuan.php` |
| Kalimat keputusan di LHU ("Lulus uji, bisa ekspor") | `app/Enums/StatusIzin.php` → `keputusan()` |
| Nama & penjelasan **Mandiri / Eksternal** | `app/Enums/JenisBayar.php` → `label()`, `keterangan()` |
| Hitungan **hari kerja** & teks lencana norma ("Sisa 2 hari kerja") | `app/Support/HariKerja.php` |
| Berapa menit dianggap **online** (bawaan 2) | `app/Models/User.php` → `ONLINE_MENIT` |
| Kecepatan chat (tarik pesan tiap 3 detik) | `public/js/app.js` → bagian "Chat langsung" |
| Pesan validasi form (bahasa Indonesia) | `lang/id/validation.php` |
| Pesan gagal login | `lang/id/auth.php` |
| Aturan isian form ajukan/bayar (panjang maks., wajib/tidak) | `app/Http/Controllers/PermohonanController.php` (blok `validate([...])`) |

### 5.5 Warna, huruf & gaya
| Yang ingin diubah | File |
|---|---|
| **Palet warna** (`laut` = biru utama, `lamun` = hijau, `karang` = merah, `surya` = kuning, `kabut` = abu) | `tailwind.config.js` |
| Gaya komponen (tombol, kartu, badge, tabel) | `resources/css/app.css` |
| Hasil CSS yang dipakai browser (**jangan diedit langsung**) | `public/css/app.css` ← dihasilkan dari 2 file di atas |
| Interaksi (cek pembaruan otomatis, konfirmasi, cegah klik ganda) | `public/js/app.js` |

### 5.6 Aturan bisnis (untuk programmer)
| Yang ingin diubah | File |
|---|---|
| Transisi status, hitung tagihan, rekap lulus/tidak lulus, nomor LHU, upload, **alur Mandiri/Eksternal**, **catat tahap lab & operator** | `app/Services/Workflow.php` |
| Kelola operator per tim (Admin Lab) | `app/Http/Controllers/Admin/TimController.php` |
| Chat: kirim/tarik pesan, belum dibaca, daftar online | `app/Http/Controllers/ChatController.php`, `app/Services/Chat.php` |
| Pencatat "terakhir aktif" (status online) | `app/Http/Middleware/CatatAktivitas.php` |
| Tabel baru revisi (norma, tahap lab, operator, chat) | `database/migrations/2026_11_11_000001_revisi_norma_tim_chat.php` |
| Ukuran maks. upload (bawaan 5 MB) | `app/Services/Workflow.php` → `MAX_UPLOAD` |
| Siapa boleh melihat/mengubah permohonan | `app/Policies/PermohonanPolicy.php` |
| Alamat URL & peran yang boleh membuka | `routes/web.php` |
| Batas percobaan (login, daftar, aksi) | `app/Providers/AppServiceProvider.php` → `RateLimiter::for(...)` |
| Header keamanan (CSP, dll.) | `app/Http/Middleware/SecurityHeaders.php` |
| Struktur tabel database | `database/migrations/2026_11_10_000001_create_sipelab_tables.php` |
| Jadwal otomatis (bersih sesi & log audit > 2 tahun) | `routes/console.php`, `app/Console/Commands/Bersihkan.php` |

> ⚠️ Ubah file di 5.6 hanya bila paham Laravel. Setelah mengubah, **jalankan pengujian** (lihat bagian 6).

---

## 6. Setelah mengubah file, jalankan apa?

Jalankan dari folder `C:\laragon\www\sipelab` (di Terminal Laragon, `php` sudah tersedia):

| Yang diubah | Perintah |
|---|---|
| File `.blade.php`, `app/`, `routes/` | Tidak perlu apa-apa — cukup **refresh** browser (di laptop) |
| `.env` atau `config/*.php` | `php artisan config:clear` |
| Warna/gaya (`tailwind.config.js`, `resources/css/app.css`) **atau menambah class Tailwind baru di Blade** | `npm install` (sekali saja) lalu `npm run build` — butuh Node.js (ada di Laragon) |
| Seeder / ingin data demo bersih | `php artisan migrate:fresh --seed` (⚠️ menghapus semua data) |
| Mengubah data pengaturan langsung di database | `php artisan cache:clear` |
| Aturan bisnis (`Workflow.php`, Policy, Controller) | `vendor\bin\phpunit` → pastikan semua **OK** |

Server production dikelola instansi; setelah kode baru dipasang di sana cukup `php artisan migrate --force`.

---

## 7. Hosting demo di Vercel (gratis)

Untuk **demo online** (bukan server resmi — server production dikelola instansi).

| Bagian | Di Vercel |
|---|---|
| PHP / Laravel | Runtime komunitas `vercel-php` (PHP 8.3), diatur di `vercel.json` + `api/index.php` |
| Database | **Neon Postgres** gratis (Vercel → Storage → Neon), otomatis mengisi `DATABASE_URL` |
| Berkas unggahan | Disimpan di database (`SIPELAB_BERKAS_DB=true`), karena Vercel tidak punya disk permanen |
| Rahasia | `APP_KEY` dan `SEED_PASSWORD` disimpan di **Environment Variables** Vercel, bukan di kode |

Alamat demo: **https://sipelab.vercel.app** · Dashboard: https://vercel.com/fayyaz9/sipelab

File terkait: `vercel.json` (rute), `api/index.php` (pintu masuk), `.vercelignore` (yang tidak diunggah), `deploy-vercel.ps1` (skrip deploy).

**Memperbarui demo setelah kode berubah** (dari folder proyek, sekali login `npx vercel@latest login`):
```powershell
powershell -ExecutionPolicy Bypass -File deploy-vercel.ps1
```
Skrip menyalin proyek **tanpa folder `.git`** lalu deploy, karena paket gratis Vercel memblokir deploy bila penulis commit Git bukan pemilik akun.

**Bila ada migrasi database baru**, jalankan migrasi ke Neon dari laptop:
```powershell
npx vercel@latest env pull .env.neon --environment=production --yes
# ambil nilai DATABASE_URL_UNPOOLED dari .env.neon, lalu:
$env:DB_CONNECTION="pgsql"; $env:DB_URL="<DATABASE_URL_UNPOOLED>"
php -d extension=pdo_pgsql artisan migrate --force
Remove-Item .env.neon   # berisi sandi database, jangan disimpan
```

Catatan teknis:
- Pengaturan aplikasi (APP_ENV, lokasi cache `/tmp`, dll.) dan rahasia (`APP_KEY`, `SEED_PASSWORD`) tersimpan sebagai **Environment Variables** di dashboard Vercel. Salinan `APP_KEY` & sandi demo ada di `.vercel/rahasia-vercel.txt` (tidak masuk Git) — **jangan sampai hilang**.
- Aplikasi memakai koneksi Neon **langsung** (`DATABASE_URL_UNPOOLED`); koneksi pooled membuat transaksi Postgres gagal.
- Akun demo di Vercel memakai **sandi acak** (bukan `Lab12345!`), lihat `.vercel/rahasia-vercel.txt`.

Batasan Vercel gratis (paket Hobby): untuk penggunaan non-komersial/demo; fungsi dibatasi waktu eksekusi; chat menarik pesan tiap 3 detik sehingga memakai kuota pemanggilan fungsi; jadwal otomatis (`schedule:run`) tidak berjalan.

---

## 8. Masalah umum

| Gejala | Solusi |
|---|---|
| `localhost:8000` tidak terbuka | Jendela `demo.bat` belum dijalankan / sudah tertutup |
| HP tidak bisa membuka `http://IP:8000` | HP & laptop harus di Wi-Fi yang sama; izinkan `php.exe` di Windows Firewall (Private) |
| Port 8000 sudah dipakai | Tutup jendela demo lain, atau `php artisan serve --port=8001` |
| Halaman error panjang berwarna | `APP_DEBUG=true` — normal saat mengembangkan; ubah ke `false` untuk demo |
| "Halaman kedaluwarsa" (419) | Sesi habis / terlalu lama diam → refresh, masuk lagi |
| Akun terkunci | Tunggu 15 menit, atau Superadmin → Pengguna → *Buka kunci* |
| Lupa sandi akun demo | Reset data: `demo.bat` pilihan **2** (sandi kembali `Lab12345!`) |
| Ubah `.env` tidak berpengaruh | `php artisan config:clear` |
| Ubah warna tidak berpengaruh | `npm run build`, lalu refresh browser dengan `Ctrl+F5` |
| Kode billing / NTPN ditolak padahal benar | Format Balai berbeda → sesuaikan `config/sipelab.php` |
| Upload ditolak | Hanya PDF/JPG/PNG asli, maks. 5 MB (file yang hanya diganti ekstensinya akan ditolak) |
| Laragon/XAMPP/Herd bentrok port 80/3306 | Pakai salah satu saja; untuk demo cukup `demo.bat` (tidak butuh Apache/MySQL) |

---

*Folder proyek: `C:\laragon\www\sipelab` · Paket asli: `Downloads\sipelab-laravel.zip`*
