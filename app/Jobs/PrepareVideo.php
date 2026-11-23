<?php

namespace App\Jobs;

use App\Models\MediaConversion;
use App\Services\TvConversions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class PrepareVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 21630;

    public function __construct(public string $key) {}

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->key))->dontRelease()->expireAfter($this->timeout + 30)];
    }

    public function handle(TvConversions $conversions): void
    {
        $conversion = MediaConversion::findOrFail($this->key);
        try {
            $conversions->run($conversion);
        } catch (Throwable $exception) {
            report($exception);
            $conversion->update(['status' => 'error', 'message' => 'A preparação falhou. Confira espaço em disco e os caminhos do FFmpeg.']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        MediaConversion::whereKey($this->key)->update(['status' => 'error', 'message' => 'O worker foi interrompido. Solicite a preparação novamente.']);
    }
}
