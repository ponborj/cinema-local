<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaRequest;
use App\Services\MediaLibrary;
use App\Services\MediaProbe;
use App\Services\Subtitles;
use App\Services\VideoResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MediaController extends Controller
{
    public function watch(MediaRequest $request, MediaLibrary $library, MediaProbe $probe, Subtitles $subtitles): View
    {
        $relative = $request->validated('path') ?? '';
        $file = $library->resolve($relative, MediaLibrary::VIDEO_EXTENSIONS);
        $metadata = ['streams' => []];
        $probeError = null;
        try {
            $metadata = $probe->inspect($file);
        } catch (HttpException $exception) {
            $probeError = $exception->getMessage();
        }
        $audio = collect($metadata['streams'])->where('codec_type', 'audio')->values()->all();
        $tracks = $subtitles->tracks($relative, $metadata);
        $title = $library->title(basename($file));
        $parent = dirname($relative) === '.' ? '' : dirname($relative);
        $duration = (float) ($metadata['format']['duration'] ?? 0);

        return view('media.watch', compact('relative', 'title', 'parent', 'audio', 'tracks', 'duration', 'probeError'));
    }

    public function stream(MediaRequest $request, MediaLibrary $library, VideoResponse $response): BinaryFileResponse
    {
        $file = $library->resolve($request->validated('path') ?? '', MediaLibrary::VIDEO_EXTENSIONS);

        return $response->file($request, $file, $library->mime($file));
    }

    public function subtitle(MediaRequest $request, Subtitles $subtitles): Response
    {
        $track = $request->validated('track');

        return response($subtitles->contents($request->validated('path') ?? '', $track === null ? null : (int) $track), 200, ['Content-Type' => 'text/vtt; charset=utf-8']);
    }
}
