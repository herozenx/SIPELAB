<?php

namespace App\Support;

use App\Enums\Status;
use App\Models\Permohonan;
use Illuminate\Support\Collection;

/**
 * Menyusun stasiun "jalur uji" sebuah permohonan:
 * - Mandiri  : Diajukan -> Pembayaran -> [lab aktif sesuai urutan] -> LHU.
 * - Eksternal: Diajukan -> [lab aktif sesuai urutan] -> LHU -> Pelunasan.
 * Setiap stasiun berstatus done | now | todo | fail.
 */
class Jalur
{
    /** @return array<int, array{nama:string, sub:?string, state:string}> */
    public static function untuk(Permohonan $p, Collection $labs): array
    {
        $eksternal = $p->eksternal();
        $st = [['nama' => 'Diajukan', 'sub' => 'Verifikasi sampel', 'kunci' => 'ajukan']];
        if (! $eksternal) {
            $st[] = ['nama' => 'Pembayaran', 'sub' => 'SIMPONI', 'kunci' => 'bayar'];
        }
        foreach ($labs as $l) {
            $st[] = ['nama' => $l->nama, 'sub' => 'Lab', 'lab_id' => $l->id, 'kunci' => 'lab'];
        }
        $st[] = ['nama' => 'LHU', 'sub' => $eksternal ? 'Validasi' : 'Validasi & terbit', 'kunci' => 'lhu'];
        if ($eksternal) {
            $st[] = ['nama' => 'Pelunasan', 'sub' => 'LHU diambil', 'kunci' => 'bayar'];
        }
        $last = count($st) - 1;
        $idx = fn (string $kunci) => collect($st)->search(fn ($s) => $s['kunci'] === $kunci);
        $labPertama = collect($st)->search(fn ($s) => $s['kunci'] === 'lab');

        $now = match ($p->status) {
            Status::Diajukan, Status::Ditolak => 0,
            Status::MenungguPembayaran, Status::MenungguKonfirmasiBayar => $idx('bayar'),
            Status::DiLab => (function () use ($st, $p, $labPertama) {
                foreach ($st as $i => $s) {
                    if (($s['lab_id'] ?? null) === $p->current_lab_id) {
                        return $i;
                    }
                }

                return $labPertama === false ? 1 : $labPertama;
            })(),
            Status::MenungguValidasi => $idx('lhu'),
            Status::Selesai => $last + 1,
        };

        foreach ($st as $i => &$s) {
            $s['state'] = match (true) {
                $p->status === Status::Ditolak && $i === 0 => 'fail',
                $i < $now => 'done',
                $i === $now => 'now',
                default => 'todo',
            };
            if ($s['kunci'] === 'bayar' && $p->status === Status::MenungguKonfirmasiBayar) {
                $s['sub'] = 'Dicek admin';
            }
            if ($s['kunci'] === 'lhu' && $eksternal && $i < $now) {
                $s['sub'] = 'Sudah jadi';
            }
            if ($i === 0 && $p->status === Status::Ditolak) {
                $s['sub'] = 'Ditolak';
            }
            unset($s['kunci']);
        }

        return $st;
    }
}
