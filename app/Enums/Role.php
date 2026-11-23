<?php

namespace App\Enums;

/**
 * Peran pengguna SILANTRO.
 * Nilai lama tetap dipakai agar data yang sudah ada tidak perlu dikonversi:
 * superadmin = Administrator, petugas_lab = Analis/Laboran.
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case AdminLab = 'admin_lab';
    case Operator = 'operator';
    case Bendahara = 'bendahara';
    case Penyelia = 'penyelia';
    case PetugasLab = 'petugas_lab';
    case PenanggungJawab = 'penanggung_jawab';
    case PenggunaJasa = 'pengguna_jasa';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Administrator',
            self::AdminLab => 'Admin Laboratorium',
            self::Operator => 'Operator',
            self::Bendahara => 'Operator Bendahara',
            self::Penyelia => 'Penyelia',
            self::PetugasLab => 'Analis/Laboran',
            self::PenanggungJawab => 'Penanggung Jawab Lab',
            self::PenggunaJasa => 'Pengguna Jasa',
        };
    }

    /** Ringkasan tugas, ditampilkan di menu Pengguna. */
    public function tugas(): string
    {
        return match ($this) {
            self::Superadmin => 'Memantau alur dari awal sampai akhir, memverifikasi permohonan, memvalidasi hasil pengujian, mengelola akun & pengaturan',
            self::AdminLab => 'Administrasi laboratorium: registrasi sampel dan pembuatan surat tugas pengujian',
            self::Operator => 'Penerimaan sampel, menetapkan jenis layanan, dan registrasi sampel ke buku sampel',
            self::Bendahara => 'Menerbitkan billing SIMPONI dan mengonfirmasi pembayaran',
            self::Penyelia => 'Menerima surat tugas bidangnya, memantau analis, dan memaraf LHP',
            self::PetugasLab => 'Menguji sampel dan menginput hasil parameter di labnya',
            self::PenanggungJawab => 'Validasi akhir dan pengesahan LHP',
            self::PenggunaJasa => 'Mengajukan permohonan uji dan membayar tagihan',
        };
    }

    /** Pilihan "Masuk sebagai" pada form login. */
    public function portal(): string
    {
        return match ($this) {
            self::Superadmin => 'superadmin',
            self::PenggunaJasa => 'pengguna_jasa',
            default => 'operator',
        };
    }

    /** Petugas kantor: boleh melihat semua permohonan beserta identitas pemohon. */
    public function isStaff(): bool
    {
        return in_array($this, [self::Superadmin, self::AdminLab, self::Operator, self::Bendahara, self::PenanggungJawab], true);
    }

    /** Petugas laboratorium yang terikat pada satu lab/bidang (uji independen, tanpa identitas klien). */
    public function isLab(): bool
    {
        return $this === self::PetugasLab || $this === self::Penyelia;
    }

    /** Peran yang wajib ditempatkan di salah satu lab. */
    public function butuhLab(): bool
    {
        return $this->isLab();
    }

    /** Boleh membuka & mencetak LHP (dokumen rahasia, hanya petugas kantor). */
    public function bolehCetakLhp(): bool
    {
        return in_array($this, [self::Superadmin, self::AdminLab, self::Operator, self::PenanggungJawab], true);
    }

    /** @return self[] */
    public static function petugas(): array
    {
        return array_values(array_filter(self::cases(), fn (self $r) => $r !== self::PenggunaJasa));
    }
}
