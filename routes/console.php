<?php

use App\Models\ExportRun;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pmb:expire-exports', function (): void {
    $count = 0;
    foreach (ExportRun::query()->where('state', 'ready')->where('expires_at', '<=', now())->lazyById(100) as $run) {
        if ($run->private_path !== null && ! Storage::disk('local')->delete($run->private_path)) {
            continue;
        }
        $run->forceFill(['state' => 'expired', 'private_path' => null])->save();
        $count++;
    }
    $this->info($count.' berkas ekspor kedaluwarsa telah dibersihkan.');
})->purpose('Membersihkan ekspor privat yang melewati masa berlaku');

Schedule::command('pmb:expire-exports')->hourly()->withoutOverlapping();
Schedule::command('pmb:prune-imports')->dailyAt('02:00')->timezone(config('pmb.business_timezone'))->withoutOverlapping();
