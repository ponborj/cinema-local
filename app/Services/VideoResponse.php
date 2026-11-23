<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class VideoResponse
{
    /** Valida o trecho pedido antes de iniciar a transmissão. */
    public function file(Request $request, string $file, string $mime): BinaryFileResponse
    {
        $range = $request->header('Range');
        $ifRange = $request->header('If-Range');
        $size = filesize($file);
        if ($request->isMethod('GET') && $range !== null && ($ifRange === null || strtotime($ifRange) === filemtime($file))) {
            $valid = preg_match('/^bytes=(\d*)-(\d*)$/iD', $range, $parts) && ($parts[1] !== '' || $parts[2] !== '');
            if ($valid) {
                $start = $parts[1] !== '' ? (int) $parts[1] : max(0, $size - (int) $parts[2]);
                $end = $parts[1] !== '' && $parts[2] !== '' ? min((int) $parts[2], $size - 1) : $size - 1;
                $valid = $size > 0 && $start <= $end && $start < $size && ! ($parts[1] === '' && (int) $parts[2] === 0);
            }
            abort_unless($valid, 416, 'Intervalo de vídeo inválido.', ['Content-Range' => 'bytes */' . $size, 'Accept-Ranges' => 'bytes']);
        }

        return response()->file($file, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'Accept-Ranges' => 'bytes']);
    }
}
