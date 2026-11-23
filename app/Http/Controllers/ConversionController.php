<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaRequest;
use App\Models\MediaConversion;
use App\Services\TvConversions;
use App\Services\VideoResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ConversionController extends Controller
{
    public function store(MediaRequest $request, TvConversions $service): JsonResponse
    {
        $audio = $request->validated('audio');
        $conversion = $service->request($request->validated('path') ?? '', $audio === null ? null : (int) $audio);

        return response()->json($conversion->publicState(), $conversion->status === 'ready' ? 200 : 202);
    }

    public function show(MediaConversion $conversion): JsonResponse
    {
        return response()->json($conversion->publicState());
    }

    public function cancel(MediaConversion $conversion): JsonResponse
    {
        MediaConversion::whereKey($conversion->id)->where('status', 'queued')
            ->update(['cancel_requested' => true, 'status' => 'cancelled']);
        MediaConversion::whereKey($conversion->id)->where('status', 'working')
            ->update(['cancel_requested' => true]);

        return response()->json($conversion->fresh()->publicState());
    }

    public function stream(Request $request, MediaConversion $conversion, TvConversions $service, VideoResponse $response): BinaryFileResponse
    {
        abort_unless($conversion->status === 'ready' && is_file($service->output($conversion->id)), 404, 'MP4 ainda não disponível.');

        return $response->file($request, $service->output($conversion->id), 'video/mp4');
    }
}
