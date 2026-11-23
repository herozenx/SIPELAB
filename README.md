# SILANTRO

Aplikasi web layanan uji mutu hasil perikanan untuk **BPPMHKP Surabaya II**.
Alur: permohonan online → verifikasi sampel + kode billing SIMPONI → NTPN + bukti setor → konfirmasi admin →
**Nekropsi → Mikrobiologi → Kimia** (diteruskan otomatis) → rekap otomatis → validasi **LHU digital** yang bisa dicek keasliannya publik.

**Stack:** PHP 8.2+ · Laravel 11 · MySQL 8 / MariaDB 10.6+ · Blade + Tailwind CSS (sudah dikompilasi) · JavaScript tanpa framework.

---

## 1. Menjalankan di laptop (demo / pengembangan)

Kebutuhan: **PHP 8.2+** dengan ekstensi `pdo_mysql` (atau `pdo_sqlite`), `mbstring`, `openssl`, `fileinfo`, `intl`.
XAMPP 8.2+ atau Laragon sudah memenuhi (di XAMPP aktifkan `extension=intl` dan `extension=fileinfo` di `php.ini`).

Folder `vendor/` dan CSS hasil build **sudah disertakan**, jadi Composer dan Node.js **tidak wajib**.

### Cara cepat (tanpa MySQL, memakai SQLite)
```bash
cp .env.example .env
# di .env: ganti DB_CONNECTION=mysql menjadi sqlite, beri tanda # pada DB_HOST s.d. DB_PASSWORD,
#          dan ubah SEED_DEMO=false menjadi SEED_DEMO=true
php artisan key:generate
php artisan migrate --seed        # jawab "yes" saat ditanya membuat database.sqlite
php artisan serve                 # buka http://localhost:8000
```

### Dengan MySQL (XAMPP/Laragon)
1. Buat database `sipelab` (utf8mb4) di phpMyAdmin.
2. `cp .env.example .env`, isi `DB_USERNAME` / `DB_PASSWORD` (XAMPP biasanya `root` tanpa sandi), set `SEED_DEMO=true`.
3. `php artisan key:generate` → `php artisan migrate --seed` → `php artisan serve`.

Reset data demo: `php artisan migrate:fresh --seed` lalu hapus isi `storage/app/private/dokumen`.

### Akun demo (hanya bila `SEED_DEMO=true`, sandi `Lab12345!`)
| Peran | Email |
|---|---|
| Administrator | superadmin@lab.local |
| Admin Laboratorium | admin@lab.local |
| Operator (loket, registrasi, surat tugas) | operator@lab.local |
| Operator Bendahara (billing SIMPONI) | bendahara@lab.local |
| Penanggung Jawab Lab (pengesahan) | pj@lab.local |
| Penyelia Organoleptik / Mikrobiologi / Kimia / Radionuklida | penyelia.organo@ / penyelia.mikro@ / penyelia.kimia@ / penyelia.radio@lab.local |
| Analis Organoleptik / Mikrobiologi / Kimia / Radionuklida | organo@ / mikro@ (dan mikro2@) / kimia@ / radio@lab.local |
| Pengguna Jasa | pengguna@lab.local, eksternal@lab.local |

---

## 2. Server production

Server production **dikelola oleh instansi**. Kebutuhan aplikasi: PHP 8.2+ (ekstensi `pdo_mysql`, `mbstring`, `openssl`,
`fileinfo`, `intl`), MySQL 8 / MariaDB 10.6+, HTTPS, document root ke folder `public/`, dan cron `php artisan schedule:run` tiap menit.
Setelah memasang kode: `php artisan migrate --force --seed` lalu `php artisan sipelab:superadmin`.

> **Penting:** `APP_KEY` dipakai untuk HMAC tanda keaslian LHP. Jika diganti, LHP lama akan terbaca "tidak cocok" di halaman verifikasi. Simpan di tempat aman dan ikut dicadangkan.

Untuk demo online gratis, lihat **Hosting demo di Vercel** di `README-SIPELAB.md`.

---

## 3. Alur dan aturan bisnis

```
Mandiri (diajukan pengguna jasa secara online):
DIAJUKAN ─(Administrator: verifikasi berkas & data)─▶ DITERIMA ─(Operator/Admin Lab: registrasi sampel, kaji ulang, kode SP.M)─▶ MENUNGGU_BILLING ─(Bendahara: billing SIMPONI)─▶
MENUNGGU_PEMBAYARAN ─(Pengguna: unggah bukti, tanpa NTPN)─▶ MENUNGGU_KONFIRMASI_BAYAR ─(Bendahara: dana masuk)─▶ SIAP_UJI

Pengujian Eksternal / Surveilen (ditetapkan Operator saat verifikasi, kode SP.PE / SP.S):
DIAJUKAN ─▶ DITERIMA ─▶ SIAP_UJI   … lalu setelah LHP disahkan: MENUNGGU_BILLING ─▶ MENUNGGU_PEMBAYARAN ─▶ MENUNGGU_KONFIRMASI_BAYAR ─▶ SELESAI

Semua jenis:
SIAP_UJI ─(Admin Lab: surat tugas otomatis, template FL/OK/SUB II/8.5.3.d, No. FPPC = kode sampel)─▶ DI_LAB (semua lab terkait paralel; Analis input hasil per parameter)
  ─▶ MENUNGGU_PEMERIKSAAN ─(Administrator: validasi hasil)─▶ MENUNGGU_PARAF ─(Penyelia tiap bidang: paraf)─▶ MENUNGGU_PENGESAHAN
  ─(Penanggung Jawab: sahkan → nomor LHP + QR)─▶ SELESAI (Mandiri) / MENUNGGU_BILLING (lainnya)
Validasi hasil tidak benar/lengkap → kembali ke pengujian (REVISI atau UJI_ULANG).
Paraf/validasi akhir tidak disetujui → PERBAIKAN (kembali ke validasi Administrator) atau UJI_ULANG (ke lab). Paraf diulang.
```
- **Kode sampel** `SP.M|SP.PE|SP.S/DDMMYY.NN`: tanggal sampel diterima, nomor urut harian per jenis layanan (mulai 01).
  Kode diberikan sejak permohonan diajukan (SP.M); saat registrasi kode tetap, kecuali jenis layanan diubah atau sampel diterima di hari lain (diberi nomor urut jenis/tanggal tersebut).
- **Parameter uji** (menu Administrator *Parameter & tarif*, data awal dari FPPCU): pengguna memilih parameter + jumlah pengujian;
  tagihan = Σ tarif parameter × jumlah + biaya administrasi. Hasil diinput per parameter (nilai, memenuhi/tidak, keterangan) dan langsung masuk LHP.
- **Paraf digital** menyimpan nama, waktu, dan sidik hasil; bila hasil berubah setelah diparaf, pengesahan ditolak.
- **LHP** bernomor urut agenda per tahun (format di *Pengaturan*, bawaan `0001/LAB/BPPMHKP.SBY.II/KI.310/XI/2026`), QR Code ke
  `/verifikasi/<kode>`, sidik SHA-256 + HMAC. LHP otomatis tersedia sebagai PDF; petugas kantor (Administrator, Admin Lab, Operator, Penanggung Jawab)
  bisa membuka sejak disahkan, pengguna jasa setelah permohonan selesai (lunas). Bukti terima sampel (PDF) tersedia setelah registrasi.
- Format kode billing (15 digit) dan NTPN (16 huruf/angka) ada di `config/sipelab.php`.
- Pembaruan real-time: halaman memeriksa `/api/versi` tiap 8 detik dan memuat ulang otomatis bila data berubah.

---

## 4. Keamanan

| Ancaman | Penanganan |
|---|---|
| Sandi bocor / lemah | bcrypt cost 12, sandi min. 8 karakter + huruf besar/kecil + angka, rehash otomatis |
| Brute force | Rate limit login (5/menit per email+IP, 20/menit per IP) + akun terkunci 15 menit setelah 5 gagal |
| User enumeration | Pesan gagal seragam + verifikasi hash tiruan agar waktu respons sama |
| Session fixation / hijack | Session ID diganti saat login, cookie HttpOnly + SameSite + Secure (HTTPS), sesi terenkripsi, idle 30 menit, batas absolut 10 jam |
| Pencabutan sesi | `auth_version` dicek tiap request + baris sesi dihapus: ganti/reset sandi, nonaktif akun, ubah peran/lab langsung mengeluarkan sesi |
| Akun baru/reset | Sandi sementara **wajib diganti** saat pertama masuk |
| CSRF | Token CSRF Laravel pada semua form |
| XSS | Semua output di-escape Blade; CSP `script-src 'self'; style-src 'self'` tanpa script/style inline |
| Hak akses (RBAC) | Middleware `role:` per route + Policy per objek |
| IDOR / enumerasi | Data milik orang lain dijawab **404**; pengguna jasa hanya miliknya; petugas hanya sampel di lab-nya |
| Race / klik ganda | Transisi status `UPDATE … WHERE status = <lama>` dalam transaksi + tombol dinonaktifkan setelah kirim |
| Upload berbahaya | Maks. 5 MB & 3 berkas, tipe dicek dari *magic bytes*, nama acak, disimpan di `storage/app/private` (di luar `public/`), diunduh lewat route berotorisasi dengan CSP sandbox |
| SQL injection | Eloquent / query binding; pencarian `LIKE` di-escape |
| NTPN / kode billing dipakai ulang | Index UNIQUE + validasi |
| Pemalsuan LHU | Sidik SHA-256 + HMAC (APP_KEY); perubahan langsung di database terdeteksi di halaman verifikasi |
| Clickjacking & header | CSP `frame-ancestors 'none'`, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS (HTTPS) |
| Kebocoran info | `APP_DEBUG=false`; petugas tidak melihat identitas klien/tagihan/hasil lab lain; pengguna melihat nilai hasil setelah LHU tervalidasi; halaman privat `Cache-Control: no-store` |
| Jejak audit | Login/gagal/terkunci, verifikasi, pembayaran, input hasil, validasi LHU, unduh dokumen, perubahan user/lab/pengaturan tercatat beserta IP |

---

## 5. Struktur kode

| Bagian | Lokasi |
|---|---|
| Aturan alur (transisi, tagihan, rekap, LHU, upload) | `app/Services/Workflow.php` |
| Otorisasi objek | `app/Policies/PermohonanPolicy.php` |
| Middleware keamanan | `app/Http/Middleware/` (header, peran, validitas sesi) |
| Controller | `app/Http/Controllers/` (+ `Admin/`) |
| Status, peran, tujuan, keputusan | `app/Enums/` |
| Skema database | `database/migrations/2026_11_10_000001_create_sipelab_tables.php` |
| Tampilan | `resources/views/` (Blade) |
| Gaya | `resources/css/app.css` → dikompilasi ke `public/css/app.css` |
| Interaksi | `public/js/app.js` |
| Pengaturan khusus aplikasi | `config/sipelab.php` |

Mengubah tampilan (butuh Node.js 18+): `npm install` lalu `npm run build` (atau `npm run watch` saat mengembangkan).

## 6. Pengujian otomatis

```bash
vendor/bin/phpunit
```
Mencakup alur lengkap sampai LHU, aturan lulus/tidak lulus, deteksi pemalsuan LHU, IDOR, pembatasan peran,
independensi petugas, klik ganda, upload palsu, NTPN ganda, penguncian akun, pencabutan sesi, sandi sementara,
dan header keamanan. Jalankan sebelum setiap perubahan dipasang ke server.

## 7. Catatan serah terima

- Repositori kode sebaiknya disimpan di akun Git milik Balai, bukan akun pribadi.
- Integrasi API SIMPONI otomatis belum ada: konfirmasi dana masih manual oleh Admin Lab (perlu akses API resmi dari Kemenkeu).
- Notifikasi email/WhatsApp belum diaktifkan (`MAIL_MAILER=log`).
- Untuk memperbarui dependensi: `composer update` (butuh koneksi ke Packagist), lalu jalankan ulang pengujian.
