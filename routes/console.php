<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('newoo:expire-stock', function () {
    $count = app(\App\Services\StockInventory::class)->releaseExpired();
    $this->info("Released {$count} expired unpaid stock reservation(s).");
})->purpose('Release expired reservations only when no payment may be pending');

\Illuminate\Support\Facades\Schedule::command('newoo:expire-stock')->everyMinute()->withoutOverlapping();
