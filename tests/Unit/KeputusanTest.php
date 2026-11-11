<?php

namespace Tests\Unit;

use App\Enums\StatusIzin;
use App\Enums\Tujuan;
use App\Services\Workflow;
use PHPUnit\Framework\TestCase;

class KeputusanTest extends TestCase
{
    public function test_kalimat_keputusan_menyesuaikan_tujuan(): void
    {
        $this->assertSame('Lulus uji, bisa ekspor', StatusIzin::Lulus->keputusan(Tujuan::Ekspor));
        $this->assertSame('Tidak lulus uji, tidak bisa beredar', StatusIzin::TidakLulus->keputusan(Tujuan::Domestik));
    }

    public function test_deteksi_tipe_berkas_dari_magic_bytes(): void
    {
        $f = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($f, "%PDF-1.7\n...");
        $this->assertSame(['application/pdf', 'pdf'], Workflow::sniff($f));
        file_put_contents($f, '<?php echo 1;');
        $this->assertNull(Workflow::sniff($f));
        unlink($f);
    }
}
