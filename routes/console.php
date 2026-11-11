<?php

use Illuminate\Support\Facades\Schedule;

// Bersihkan sesi kedaluwarsa & log audit lebih dari 2 tahun (aktifkan cron `php artisan schedule:run` tiap menit).
Schedule::command('sipelab:bersihkan')->dailyAt('01:30');
