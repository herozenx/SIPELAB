<?php

namespace App\Support;

use App\Enums\Status;
use App\Models\Permohonan;
use Illuminate\Support\Collection;

/**
 * Menyusun stasiun "jalur uji" sebuah permohonan:
 * - Mandiri  : Verifikasi -> Registrasi -> Pembayaran -> Surat tugas -> Pengujian -> Validasi -> Paraf -> Pengesahan.
 * - Lainnya  : Verifikasi -> Registrasi -> Surat tugas -> Pengujian -> Validasi -> Paraf -> Pengesahan -> Pelunasan.
 * Setiap stasiun berstatus done | now | todo | fail.
 */
class Jalur
{
    /** @return array<int, array{nama:string, sub:?string, state:string}> */
    public static function untuk(Permohonan $p, ?Collection $labs = null): array
    {
        $mandiri = $p->mandiri();
        $st = [['nama' => 'Verifikasi', 'sub' => 'Administrator', 'kunci' => 'ajukan'], ['nama' => 'Registrasi', 'sub' => 'Kode sampel', 'kunci' => 'registrasi']];
        if ($mandiri) {
            $st[] = ['nama' => 'Pembayaran', 'sub' => 'SIMPONI', 'kunci' => 'bayar'];
        }
        $st[] = ['nama' => 'Surat tugas', 'sub' => 'Ke penyelia', 'kunci' => 'surat'];
        $st[] = ['nama' => 'Pengujian', 'sub' => 'Lab', 'kunci' => 'lab'];
        $st[] = ['nama' => 'Validasi', 'sub' => 'Administrator', 'kunci' => 'periksa'];
        $st[] = ['nama' => 'Paraf', 'sub' => 'Penyelia', 'kunci' => 'paraf'];
        $st[] = ['nama' => 'Pengesahan', 'sub' => 'Penanggung jawab', 'kunci' => 'sah'];
        if (! $mandiri) {
            $st[] = ['nama' => 'Pelunasan', 'sub' => 'SIMPONI', 'kunci' => 'bayar'];
        }
        $last = count($st) - 1;
        $idx = fn (string $kunci) => collect($st)->search(fn ($s) => $s['kunci'] === $kunci);

        $now = match ($p->status) {
            Status::Diajukan, Status::Ditolak => 0,
            Status::Diterima => $idx('registrasi'),
            Status::MenungguBilling, Status::MenungguPembayaran, Status::MenungguKonfirmasiBayar => $idx('bayar'),
            Status::SiapUji => $idx('surat'),
            Status::DiLab => $idx('lab'),
            Status::MenungguPemeriksaan => $idx('periksa'),
            Status::MenungguParaf => $idx('paraf'),
            Status::MenungguPengesahan => $idx('sah'),
            Status::Selesai => $last + 1,
        };

        if ($p->status === Status::DiLab && $p->relationLoaded('tahap') && $p->tahap->isNotEmpty()) {
            $st[$idx('lab')]['sub'] = $p->tahap->whereNotNull('selesai_at')->count().'/'.$p->tahap->count().' lab selesai';
        }

        foreach ($st as $i => &$s) {
            $s['state'] = match (true) {
                $p->status === Status::Ditolak && $i === 0 => 'fail',
                $i < $now => 'done',
                $i === $now => 'now',
                default => 'todo',
            };
            if ($s['kunci'] === 'bayar' && $p->status === Status::MenungguBilling && $i === $now) {
                $s['sub'] = 'Menunggu billing';
            }
            if ($s['kunci'] === 'bayar' && $p->status === Status::MenungguKonfirmasiBayar && $i === $now) {
                $s['sub'] = 'Dicek bendahara';
            }
            if ($i === 0 && $p->status === Status::Ditolak) {
                $s['sub'] = 'Ditolak';
            }
            unset($s['kunci']);
        }

        return $st;
    }
}
