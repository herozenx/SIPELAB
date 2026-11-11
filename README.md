# SIPELAB — Sistem Pelayanan, Pengujian & Sertifikasi Laboratorium

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
| Akun | "Masuk sebagai" | Email |
|---|---|---|
| Superadmin | Superadmin | superadmin@lab.local |
| Admin Lab | Operator | admin@lab.local |
| Petugas Nekropsi / Mikrobiologi / Kimia | Operator | nekropsi@ / mikro@ / kimia@lab.local |
| Pengguna Jasa | Pengguna jasa | pengguna@lab.local |

---

## 2. Server production

Server production **dikelola oleh instansi**. Kebutuhan aplikasi: PHP 8.2+ (ekstensi `pdo_mysql`, `mbstring`, `openssl`,
`fileinfo`, `intl`), MySQL 8 / MariaDB 10.6+, HTTPS, document root ke folder `public/`, dan cron `php artisan schedule:run` tiap menit.
Setelah memasang kode: `php artisan migrate --force --seed` lalu `php artisan sipelab:superadmin`.

> **Penting:** `APP_KEY` dipakai untuk HMAC tanda keaslian LHU. Jika diganti, LHU lama akan terbaca "tidak cocok" di halaman verifikasi. Simpan di tempat aman dan ikut dicadangkan.

Untuk demo online gratis, lihat **Hosting demo di Vercel** di `README-SIPELAB.md`.

---

## 3. Alur dan aturan bisnis

```
DIAJUKAN ─(Admin: sampel sesuai + kode billing)─▶ MENUNGGU_PEMBAYARAN ─(Pengguna: NTPN + bukti)─▶ MENUNGGU_KONFIRMASI_BAYAR
   └─(Admin: tolak + alasan)─▶ DITOLAK                     ▲──────────(Admin: bukti ditolak + alasan)──────────┤
                                                                                                    (Admin: dana masuk)
DI_LAB[lab urutan 1] ─▶ DI_LAB[lab urutan 2] ─▶ … ─▶ MENUNGGU_VALIDASI ─(Admin: terbitkan)─▶ SELESAI (LHU terbit)
```
- Semua hasil **negatif** → *Lulus uji, bisa beredar/ekspor/impor* (sesuai tujuan). Ada **positif** → *Tidak lulus uji*.
- Lab, urutan, dan tarif diatur Superadmin (menu *Lab & tarif*); tagihan = Σ tarif lab aktif × jumlah sampel + biaya administrasi.
- Format kode billing (15 digit) dan NTPN (16 huruf/angka) ada di `config/sipelab.php` — sesuaikan bila format Balai berbeda.
- Pembaruan real-time: halaman memeriksa `/api/versi` tiap 8 detik dan memuat ulang otomatis bila data berubah
  (tidak memuat ulang saat pengguna sedang mengisi form; muncul tombol "Muat ulang").
- LHU memiliki nomor urut per tahun, kode verifikasi publik (`/verifikasi/<kode>`), sidik SHA-256, dan HMAC.

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
| Pengaturan khusus SIPELAB | `config/sipelab.php` |

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
