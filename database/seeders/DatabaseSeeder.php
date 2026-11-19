<?php

namespace Database\Seeders;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Models\Lab;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data dasar (lab, tarif, norma waktu, pengaturan) selalu diisi.
     * Akun demo hanya dibuat bila SEED_DEMO=true; di production buat superadmin lewat `php artisan sipelab:superadmin`.
     */
    public function run(): void
    {
        // Lab mengikuti LHPS instansi. [nama, urutan, tarif per sampel, norma hari kerja]
        // Tarif Organoleptik & Radionuklida diisi Superadmin di menu Lab & tarif.
        foreach ([['Mikrobiologi', 1, 150000, 3], ['Kimia', 2, 200000, 2], ['Organoleptik', 3, 0, 1], ['Radionuklida', 4, 0, 1]] as [$nama, $urut, $tarif, $norma]) {
            Lab::query()->firstOrCreate(['nama' => $nama], ['urutan' => $urut, 'tarif' => $tarif, 'norma_hari' => $norma, 'aktif' => true]);
        }
        // Katalog parameter (kolom Buku Sampel + teks LHP asli); tidak menimpa yang sudah diedit.
        (require __DIR__.'/katalog_parameter.php')();
        foreach (Setting::DEFAULTS as $k => $v) {
            Setting::query()->firstOrCreate(['key' => $k], ['value' => $v]);
        }

        if (! filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Akun demo tidak dibuat (SEED_DEMO=false). Jalankan: php artisan sipelab:superadmin');

            return;
        }

        $pw = env('SEED_PASSWORD', 'Lab12345!');
        $lab = fn ($n) => Lab::query()->where('nama', $n)->value('id');
        // [nama, email, peran, lab, instansi, jenis pengguna jasa]
        $akun = [
            ['Super Admin', 'superadmin@lab.local', Role::Superadmin, null, null, null],
            ['Admin Lab', 'admin@lab.local', Role::AdminLab, null, null, null],
            ['Administrator', 'administrasi@lab.local', Role::Administrasi, null, null, null],
            ['Petugas Mikrobiologi', 'mikro@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Dewi Mikrobiologi', 'mikro2@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Petugas Kimia', 'kimia@lab.local', Role::PetugasLab, $lab('Kimia'), null, null],
            ['Petugas Organoleptik', 'organoleptik@lab.local', Role::PetugasLab, $lab('Organoleptik'), null, null],
            ['Petugas Radionuklida', 'radio@lab.local', Role::PetugasLab, $lab('Radionuklida'), null, null],
            ['Rini Hapsari', 'pengguna@lab.local', Role::PenggunaJasa, null, 'CV Bahari Demo', JenisBayar::Mandiri],
            ['Agus Santoso', 'eksternal@lab.local', Role::PenggunaJasa, null, 'PT Samudra Eksternal Demo', JenisBayar::Eksternal],
        ];
        foreach ($akun as [$nama, $email, $role, $labId, $instansi, $tipe]) {
            User::query()->firstOrCreate(['email' => $email], [
                'nama' => $nama, 'password' => $pw, 'role' => $role, 'lab_id' => $labId,
                'instansi' => $instansi, 'tipe_pengguna' => $tipe,
                'telepon' => $role === Role::PenggunaJasa ? '0812-3456-7890' : null,
            ]);
        }
        $this->command?->info("Akun demo dibuat. Kata sandi semua akun: {$pw}");
    }
}
