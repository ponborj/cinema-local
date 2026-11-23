<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaRequest;
use App\Services\MediaLibrary;
use Illuminate\Contracts\View\View;

final class LibraryController extends Controller
{
    public function __invoke(MediaRequest $request, MediaLibrary $library): View
    {
        $catalog = $library->browse($request->validated('folder') ?? '');
        $breadcrumbs = [];
        $current = [];
        foreach (array_filter(explode('/', $catalog['folder'])) as $part) {
            $current[] = $part;
            $breadcrumbs[] = ['title' => $library->title($part), 'folder' => implode('/', $current)];
        }
        $ips = config('media.advertised_ip') ? [config('media.advertised_ip')] : (gethostbynamel(gethostname()) ?: []);
        $portSuffix = $request->getPort() === 80 ? '' : ':'.$request->getPort();
        $addresses = array_map(fn (string $ip): string => 'http://'.$ip.$portSuffix, array_filter(array_unique($ips), fn (string $ip): bool => ! str_starts_with($ip, '127.')));

        return view('library.index', compact('catalog', 'breadcrumbs', 'addresses'));
    }
}
