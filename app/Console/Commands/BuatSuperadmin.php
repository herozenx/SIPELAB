<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Membuat akun superadmin pertama di server production (tanpa akun demo). */
class BuatSuperadmin extends Command
{
    protected $signature = 'sipelab:superadmin';

    protected $description = 'Buat akun superadmin baru';

    public function handle(): int
    {
        $nama = text('Nama lengkap', required: true);
        $email = mb_strtolower(text('Email', required: true, validate: fn ($v) => User::query()->where('email', mb_strtolower($v))->exists() ? 'Email sudah terdaftar.' : null));
        $pass = password('Kata sandi (min. 8, huruf besar, huruf kecil, angka)', required: true);

        $v = Validator::make(['email' => $email, 'password' => $pass], ['email' => 'email', 'password' => Password::defaults()]);
        if ($v->fails()) {
            $this->error(implode(' ', $v->errors()->all()));

            return self::FAILURE;
        }
        User::query()->create(['nama' => $nama, 'email' => $email, 'password' => $pass, 'role' => Role::Superadmin]);
        $this->info("Superadmin {$email} dibuat.");

        return self::SUCCESS;
    }
}
