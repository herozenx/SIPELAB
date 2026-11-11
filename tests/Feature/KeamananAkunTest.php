<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeamananAkunTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_berhasil_dan_salah_portal_ditolak(): void
    {
        $this->post('/masuk', ['email' => 'admin@lab.local', 'password' => 'Lab12345!', 'portal' => 'pengguna_jasa'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/masuk', ['email' => 'admin@lab.local', 'password' => 'Lab12345!', 'portal' => 'operator'])->assertRedirect('/beranda');
        $this->assertAuthenticated();
    }

    public function test_akun_terkunci_setelah_lima_kali_gagal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['email' => 'kimia@lab.local', 'password' => 'salah', 'portal' => 'operator']);
        }
        $this->assertNotNull(User::query()->where('email', 'kimia@lab.local')->value('locked_until'));
        \Illuminate\Support\Facades\RateLimiter::clear('login'); // lepas pembatas laju agar penguncian akun yang diuji
        $this->app['cache']->store()->flush();
        $this->post('/masuk', ['email' => 'kimia@lab.local', 'password' => 'Lab12345!', 'portal' => 'operator'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sesi_dicabut_saat_akun_dinonaktifkan(): void
    {
        $u = User::query()->where('email', 'mikro@lab.local')->firstOrFail();
        $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()])->get('/beranda')->assertOk();
        $u->update(['aktif' => false]);
        $u->revokeSessions();
        $this->actingAs($u->fresh())->withSession(['auth_version' => 0, 'login_at' => time()])->get('/beranda')->assertRedirect('/masuk');
    }

    public function test_sandi_sementara_wajib_diganti(): void
    {
        $u = User::query()->where('email', 'nekropsi@lab.local')->firstOrFail();
        $u->forceFill(['wajib_ganti_password' => true])->save();
        $this->actingAs($u)->withSession(['auth_version' => $u->auth_version, 'login_at' => time()])->get('/beranda')->assertRedirect('/akun');
    }

    public function test_header_keamanan_terpasang(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_pendaftaran_menolak_sandi_lemah(): void
    {
        $this->post('/daftar', ['nama' => 'Budi', 'instansi' => 'UD Laut', 'email' => 'budi@x.id', 'telepon' => '081234567890', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertSessionHasErrors('password');
    }
}
