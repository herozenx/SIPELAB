<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Katalog parameter uji per lab (kolom Buku Sampel), dengan teks persyaratan & metode dari LHP asli. */
class ParameterUji extends Model
{
    protected $table = 'parameter_uji';

    protected $guarded = ['id'];

    /** Sub-item sensory test, persis kolom LHPS Organoleptik (form operator). */
    public const SUB_ITEM_ORGANOLEPTIK = [
        ['kelompok' => 'Keadaan Beku', 'item' => ['Lapisan Es', 'Dehidrasi', 'Diskolorisasi']],
        ['kelompok' => 'Thawing', 'item' => ['Kenampakan', 'Bau', 'Tekstur', 'Daging', 'Rasa']],
        ['kelompok' => 'Keadaan Segar', 'item' => ['Kenampakan Mata', 'Kenampakan Insang', 'Kenampakan Lendir', 'Daging', 'Bau', 'Tekstur']],
        ['kelompok' => 'Produk Kering', 'item' => ['Kenampakan', 'Bau', 'Rasa', 'Tekstur', 'Jamur']],
        ['kelompok' => 'Produk Kaleng', 'item' => ['Kenampakan', 'Bau', 'Rasa', 'Tekstur', 'Konsistensi']],
    ];

    protected function casts(): array
    {
        return ['pilihan' => 'array', 'persyaratan' => 'array', 'sub_item' => 'array', 'ulangan' => 'integer', 'urutan' => 'integer', 'aktif' => 'boolean'];
    }

    /**
     * Susun isi sel LHP dari nilai sub-item: [indeks kelompok => [indeks item => nilai]].
     * Hanya item yang diisi yang dicetak; kelompok diberi huruf A, B, ..., item diberi nomor,
     * dan baris hasil sejajar dengan baris parameter (baris judul berisi kosong), seperti LHP asli.
     *
     * @return array{nama:string, ulangan:array<int,string>, rincian:array}|null
     */
    public function susunSubItem(array $nilai): ?array
    {
        $nama = [$this->nama.' :'];
        $hasil = [''];
        $rincian = [];
        $huruf = 0;
        foreach ($this->sub_item ?? [] as $g => $grup) {
            $isi = [];
            foreach ($grup['item'] as $i => $item) {
                $v = trim((string) ($nilai[$g][$i] ?? ''));
                if ($v !== '') {
                    $isi[] = [$item, mb_substr($v, 0, 20)];
                    $rincian[$g][$i] = mb_substr($v, 0, 20);
                }
            }
            if (! $isi) {
                continue;
            }
            $nama[] = chr(65 + $huruf++).'. '.$grup['kelompok'];
            $hasil[] = '';
            foreach ($isi as $n => [$item, $v]) {
                $nama[] = '   '.($n + 1).'. '.$item;
                $hasil[] = $v;
            }
        }

        return $rincian ? ['nama' => implode("\n", $nama), 'ulangan' => $hasil, 'rincian' => $rincian] : null;
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(Lab::class);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    /** Nama tanpa penanda miring, untuk tampilan web. */
    public function namaPolos(): string
    {
        return str_replace('_', '', $this->nama);
    }
}
