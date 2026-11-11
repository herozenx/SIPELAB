<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Bersihkan extends Command
{
    protected $signature = 'sipelab:bersihkan {--tahun-audit=2 : Simpan log audit selama N tahun}';

    protected $description = 'Hapus sesi kedaluwarsa dan log audit yang melewati masa simpan';

    public function handle(): int
    {
        $sesi = DB::table('sessions')->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime'))->timestamp)->delete();
        $audit = AuditLog::query()->where('created_at', '<', now()->subYears((int) $this->option('tahun-audit')))->delete();
        $this->info("Sesi dihapus: {$sesi}. Log audit dihapus: {$audit}.");

        return self::SUCCESS;
    }
}
