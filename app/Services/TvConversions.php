<?php

namespace App\Services;

use App\Jobs\PrepareVideo;
use App\Models\MediaConversion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class TvConversions
{
    public function __construct(private MediaLibrary $library, private MediaProbe $probe) {}

    public function fingerprint(string $file, ?int $audio): string
    {
        clearstatcache(true, $file);

        return hash('sha256', $file.':'.filesize($file).':'.filemtime($file).':'.$audio.':laravel-tv-v1');
    }

    public function output(string $key): string
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $key), 404);

        return config('media.converted_directory').DIRECTORY_SEPARATOR.$key.'.mp4';
    }

    public function request(string $relative, ?int $audio): MediaConversion
    {
        $file = $this->library->resolve($relative, MediaLibrary::VIDEO_EXTENSIONS);
        $tracks = collect($this->probe->inspect($file)['streams'])->where('codec_type', 'audio');
        if ($audio === null) {
            $portuguese = $tracks->first(fn ($track) => in_array(strtolower($track['tags']['language'] ?? ''), ['por', 'pt', 'pt-br'], true));
            $audio = ($portuguese ?? $tracks->first())['index'] ?? null;
        }
        abort_unless($audio === null || $tracks->contains('index', $audio), 422, 'Faixa de áudio inválida.');
        $key = $this->fingerprint($file, $audio);

        return Cache::lock('prepare-request:'.$key, 10)->block(3, function () use ($key, $relative, $audio): MediaConversion {
            $conversion = MediaConversion::firstOrNew(['id' => $key]);
            if ($conversion->exists && in_array($conversion->status, ['queued', 'working'], true)) {
                return $conversion;
            }
            if (is_file($this->output($key))) {
                $conversion->fill(['path' => $relative, 'audio_index' => $audio, 'status' => 'ready', 'percent' => 100])->save();

                return $conversion;
            }
            $conversion->fill(['path' => $relative, 'audio_index' => $audio, 'status' => 'queued', 'percent' => 0, 'cancel_requested' => false, 'message' => null])->save();
            PrepareVideo::dispatch($key);

            return $conversion;
        });
    }

    public function run(MediaConversion $conversion): void
    {
        $conversion->refresh();
        if ($conversion->cancel_requested || ! in_array($conversion->status, ['queued', 'working'], true)) {
            return;
        }
        $file = $this->library->resolve($conversion->path, MediaLibrary::VIDEO_EXTENSIONS);
        abort_unless($this->fingerprint($file, $conversion->audio_index) === $conversion->id, 422, 'O arquivo mudou desde a solicitação. Prepare uma nova cópia.');
        $metadata = $this->probe->inspect($file);
        $video = collect($metadata['streams'])->firstWhere('codec_type', 'video');
        abort_unless($video, 422, 'Arquivo sem faixa de vídeo.');
        File::ensureDirectoryExists(config('media.converted_directory'));
        $output = $this->output($conversion->id);
        $partial = $output.'.partial.mp4';
        $copy = $video['codec_name'] === 'h264' && ($video['pix_fmt'] ?? '') === 'yuv420p' && ($video['width'] ?? 99999) <= 1920 && ($video['height'] ?? 99999) <= 1080;
        $arguments = [config('media.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-i', $file, '-map', '0:v:0'];
        if ($conversion->audio_index !== null) {
            array_push($arguments, '-map', '0:'.$conversion->audio_index);
        }
        array_push($arguments, '-c:v', $copy ? 'copy' : 'libx264');
        if (! $copy) {
            array_push($arguments, '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p', '-vf', "scale=w='min(1920,iw)':h='min(1080,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2", '-threads', '4');
        }
        array_push($arguments, '-c:a', 'aac', '-ac', '2', '-b:a', '192k', '-sn', '-dn', '-movflags', '+faststart', '-progress', 'pipe:1', $partial);
        $conversion->update(['status' => 'working', 'percent' => 0, 'message' => null]);
        $process = new Process($arguments);
        $process->setTimeout(21600);
        $duration = (float) ($metadata['format']['duration'] ?? 0);
        $lastUpdate = 0;
        $progress = '';
        try {
            $process->start();
            while ($process->isRunning()) {
                usleep(200000);
                $progress .= $process->getIncrementalOutput();
                if (microtime(true) - $lastUpdate >= 1) {
                    $lastUpdate = microtime(true);
                    $conversion->refresh();
                    if ($conversion->cancel_requested) {
                        $process->stop(1);
                        $conversion->update(['status' => 'cancelled', 'message' => 'Preparação cancelada.']);

                        return;
                    }
                    preg_match_all('/out_time_us=(\d+)/', $progress, $matches);
                    if ($duration > 0 && ! empty($matches[1])) {
                        $conversion->update(['percent' => min(99, (int) round(end($matches[1]) / 1000000 / $duration * 100))]);
                    }
                    $progress = substr($progress, -200);
                    $process->clearOutput();
                }
                $process->checkTimeout();
            }
            $conversion->refresh();
            if ($conversion->cancel_requested) {
                $conversion->update(['status' => 'cancelled', 'message' => 'Preparação cancelada.']);

                return;
            }
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('FFmpeg: '.substr($process->getErrorOutput(), -4000));
            }
            if (! rename($partial, $output)) {
                throw new \RuntimeException('Não foi possível salvar o MP4.');
            }
            $conversion->update(['status' => 'ready', 'percent' => 100]);
        } finally {
            if ($process->isRunning()) {
                $process->stop(1);
            }
            if (is_file($partial)) {
                unlink($partial);
            }
        }
    }
}
