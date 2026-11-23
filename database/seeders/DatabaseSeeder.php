<?php

namespace Database\Seeders;

use App\Enums\JenisBayar;
use App\Enums\Role;
use App\Models\Lab;
use App\Models\ParameterUji;
use App\Models\Setting;
use App\Models\User;
use App\Support\ParameterBawaan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data dasar (lab, tarif, norma waktu, pengaturan) selalu diisi.
     * Akun demo hanya dibuat bila SEED_DEMO=true; di production buat superadmin lewat `php artisan sipelab:superadmin`.
     */
    public function run(): void
    {
        // Lab/bidang dan parameter uji bawaan (FPPCU) juga dibuat oleh migrasi revisi 2; di sini hanya memastikan.
        foreach (ParameterBawaan::LAB as [$nama, $urut, $norma]) {
            Lab::query()->firstOrCreate(['nama' => $nama], ['urutan' => $urut, 'tarif' => 0, 'norma_hari' => $norma, 'aktif' => true]);
        }
        foreach (ParameterBawaan::daftar() as $i => $p) {
            ParameterUji::query()->firstOrCreate(['kode' => $p['kode']], collect($p)->except(['kode', 'lab'])->all() + [
                'lab_id' => Lab::query()->where('nama', $p['lab'])->value('id'), 'aktif' => true, 'urutan' => $i + 1,
            ]);
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
        // [nama, email, peran, lab, instansi, jenis pengguna jasa]  (nama petugas fiktif)
        $akun = [
            ['Administrator', 'superadmin@lab.local', Role::Superadmin, null, null, null],
            ['Admin Laboratorium', 'admin@lab.local', Role::AdminLab, null, null, null],
            ['Operator Loket', 'operator@lab.local', Role::Operator, null, null, null],
            ['Bendahara Penerimaan', 'bendahara@lab.local', Role::Bendahara, null, null, null],
            ['Penanggung Jawab Lab', 'pj@lab.local', Role::PenanggungJawab, null, null, null],
            ['Penyelia Organoleptik', 'penyelia.organo@lab.local', Role::Penyelia, $lab('Organoleptik'), null, null],
            ['Penyelia Mikrobiologi', 'penyelia.mikro@lab.local', Role::Penyelia, $lab('Mikrobiologi'), null, null],
            ['Penyelia Kimia', 'penyelia.kimia@lab.local', Role::Penyelia, $lab('Kimia'), null, null],
            ['Penyelia Radionuklida', 'penyelia.radio@lab.local', Role::Penyelia, $lab('Radionuklida'), null, null],
            ['Analis Organoleptik', 'organo@lab.local', Role::PetugasLab, $lab('Organoleptik'), null, null],
            ['Analis Mikrobiologi', 'mikro@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Dewi Mikrobiologi', 'mikro2@lab.local', Role::PetugasLab, $lab('Mikrobiologi'), null, null],
            ['Analis Kimia', 'kimia@lab.local', Role::PetugasLab, $lab('Kimia'), null, null],
            ['Analis Radionuklida', 'radio@lab.local', Role::PetugasLab, $lab('Radionuklida'), null, null],
            ['Rini Hapsari', 'pengguna@lab.local', Role::PenggunaJasa, null, 'CV Bahari Demo', JenisBayar::Mandiri],
            ['Agus Santoso', 'eksternal@lab.local', Role::PenggunaJasa, null, 'PT Samudra Eksternal Demo', JenisBayar::Mandiri],
        ];
        foreach ($akun as [$nama, $email, $role, $labId, $instansi, $tipe]) {
            User::query()->firstOrCreate(['email' => $email], [
                'nama' => $nama, 'password' => $pw, 'role' => $role, 'lab_id' => $labId,
                'instansi' => $instansi, 'tipe_pengguna' => $tipe,
                'telepon' => $role === Role::PenggunaJasa ? '0812-3456-7890' : null,
                'alamat' => $role === Role::PenggunaJasa ? 'Jl. Rungkut Madya, Surabaya' : null,
                'nip' => $role === Role::PenanggungJawab ? '198001012005012001' : null,
            ]);
        }
        $this->command?->info("Akun demo dibuat. Kata sandi semua akun: {$pw}");
    }
}
