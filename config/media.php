<?php

return [
    'directory' => env('MEDIA_DIRECTORY') ?: storage_path('app/private/library'),
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    'converted_directory' => storage_path('app/private/converted'),
    'port' => (int) env('MEDIA_PORT', 80),
    'advertised_ip' => env('MEDIA_ADVERTISED_IP'),
    'allowed_hosts' => array_filter(explode(',', env('MEDIA_ALLOWED_HOSTS', 'localhost,127.0.0.1,cinema-local.test'))),
];
