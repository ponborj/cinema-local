<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

final class MediaProbe
{
    /** Lê e guarda em cache os dados de áudio e vídeo. */
    public function inspect(string $file): array
    {
        $key = 'media-probe:' . hash('sha256', $file . ':' . filesize($file) . ':' . filemtime($file));

        return Cache::remember($key, now()->addDay(), function () use ($file): array {
            $process = new Process([config('media.ffprobe'), '-v', 'error', '-show_entries', 'stream=index,codec_type,codec_name,pix_fmt,width,height:stream_tags=language,title:format=duration', '-of', 'json', $file]);
            $process->setTimeout(20);
            try {
                $process->mustRun();
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                abort_unless(isset($result['streams']), 422, 'Não foi possível analisar o vídeo.');

                return $result;
            } catch (ExceptionInterface | \JsonException $exception) {
                report($exception);
                abort(422, 'Não foi possível analisar o vídeo. Confira FFMPEG_BINARY e FFPROBE_BINARY no .env.');
            }
        });
    }
}
