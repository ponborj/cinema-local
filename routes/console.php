<?php

use App\Models\MediaConversion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cinema:stop-worker', function () {
    MediaConversion::where('status', 'working')->update(['cancel_requested' => true]);
    $this->call('queue:restart');
})->purpose('Cancel the active conversion and stop workers after their current job');
