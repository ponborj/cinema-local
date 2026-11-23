<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

final class Subtitles
{
    private const TEXT_CODECS = ['subrip', 'webvtt', 'ass', 'ssa', 'mov_text'];

    public function __construct(private MediaLibrary $library, private MediaProbe $probe) {}

    public function tracks(string $relative, array $metadata): array
    {
        $file = $this->library->resolve($relative, MediaLibrary::VIDEO_EXTENSIONS);
        $stem = mb_strtolower(pathinfo($file, PATHINFO_FILENAME));
        $tracks = [];
        foreach (new \DirectoryIterator(dirname($file)) as $entry) {
            $name = mb_strtolower($entry->getFilename());
            if ($entry->isFile() && in_array(strtolower($entry->getExtension()), ['srt', 'vtt'], true) && (mb_strtolower($entry->getBasename('.' . $entry->getExtension())) === $stem || str_starts_with($name, $stem . '.'))) {
                $tracks[] = ['label' => $entry->getFilename(), 'url' => route('media.subtitle', ['path' => $this->library->relative($entry->getPathname())], false)];
            }
        }
        foreach ($metadata['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'subtitle' && in_array($stream['codec_name'], self::TEXT_CODECS, true)) {
                $tracks[] = ['label' => $stream['tags']['title'] ?? $stream['tags']['language'] ?? 'Legenda ' . $stream['index'], 'url' => route('media.subtitle', ['path' => $relative, 'track' => $stream['index']], false)];
            }
        }

        return $tracks;
    }

    /** Entrega a legenda no formato aceito pelo navegador. */
    public function contents(string $relative, ?int $track): string
    {
        if ($track !== null) {
            $file = $this->library->resolve($relative, MediaLibrary::VIDEO_EXTENSIONS);
            $streams = $this->probe->inspect($file)['streams'];
            abort_unless(collect($streams)->contains(fn($stream) => $stream['index'] === $track && $stream['codec_type'] === 'subtitle' && in_array($stream['codec_name'], self::TEXT_CODECS, true)), 422, 'Legenda inválida.');
            $key = 'subtitle:' . hash('sha256', $file . ':' . filesize($file) . ':' . filemtime($file) . ':' . $track);

            return Cache::remember($key, now()->addDay(), function () use ($file, $track): string {
                $process = new Process([config('media.ffmpeg'), '-v', 'error', '-nostdin', '-i', $file, '-map', '0:' . $track, '-f', 'webvtt', 'pipe:1']);
                $process->setTimeout(30);
                $process->mustRun(function () use ($process): void {
                    if (strlen($process->getOutput()) > 8 * 1024 * 1024) {
                        $process->stop();
                        abort(413, 'Legenda muito grande.');
                    }
                });

                return $process->getOutput();
            });
        }
        $file = $this->library->resolve($relative, ['srt', 'vtt']);
        abort_if(filesize($file) > 8 * 1024 * 1024, 413, 'Legenda muito grande.');
        $text = file_get_contents($file);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return $this->toVtt($text);
    }

    public function toVtt(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $text));

        return str_starts_with($text, 'WEBVTT') ? $text : "WEBVTT\n\n" . preg_replace('/(\d{2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $text);
    }
}
