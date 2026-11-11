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
        // [nama, urutan, tarif per sampel, norma hari kerja] — total norma 6 hari kerja
        foreach ([['Nekropsi', 1, 75000, 1], ['Mikrobiologi', 2, 150000, 3], ['Kimia', 3, 200000, 2]] as [$nama, $urut, $tarif, $norma]) {
            Lab::query()->firstOrCreate(['nama' => $nama], ['urutan' => $urut, 'tarif' => $tarif, 'norma_hari' => $norma, 'aktif' => true]);
        }
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
            ['Petugas Nekropsi', 'nekropsi@lab.local', Role::PetugasLab, $lab('Nekropsi'), null, null],
            ['Petugas Mikrobiologi', 'mikro@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Dewi Mikrobiologi', 'mikro2@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Petugas Kimia', 'kimia@lab.local', Role::PetugasLab, $lab('Kimia'), null, null],
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
