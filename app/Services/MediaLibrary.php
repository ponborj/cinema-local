<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MediaLibrary
{
    public const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'mkv', 'webm', 'mov', 'avi', 'ts', 'm2ts', 'mpg', 'mpeg'];

    public function root(): string
    {
        $root = realpath(config('media.directory'));
        abort_unless($root && is_dir($root), 503, 'A pasta da biblioteca está indisponível. Confira MEDIA_DIRECTORY no arquivo .env.');

        return $root;
    }

    public function resolve(string $relative = '', ?array $extensions = null): string
    {
        abort_if(str_contains($relative, "\0") || str_contains($relative, ':') || preg_match('~^[\\\\/]~', $relative), 403, 'Caminho não permitido.');
        $relative = str_replace('\\', '/', $relative);
        abort_if(in_array('..', explode('/', $relative), true), 403, 'Caminho não permitido.');
        $root = $this->root();
        $resolved = realpath($root.DIRECTORY_SEPARATOR.$relative);
        abort_unless($resolved !== false, 404, 'Arquivo ou pasta indisponível.');
        $normalize = static fn (string $value): string => PHP_OS_FAMILY === 'Windows' ? mb_strtolower(str_replace('\\', '/', $value)) : $value;
        $base = rtrim($normalize($root), '/');
        $target = $normalize($resolved);
        abort_unless($target === $base || str_starts_with($target, $base.'/'), 403, 'Caminho não permitido.');
        if ($extensions !== null) {
            abort_unless(is_file($resolved) && in_array(strtolower(pathinfo($resolved, PATHINFO_EXTENSION)), $extensions, true), 403, 'Tipo de arquivo não permitido.');
        }

        return $resolved;
    }

    public function relative(string $absolute): string
    {
        return str_replace('\\', '/', ltrim(substr($absolute, strlen($this->root())), '\\/'));
    }

    public function browse(string $relative = ''): array
    {
        $directory = $this->resolve($relative);
        abort_unless(is_dir($directory), 404, 'Pasta indisponível.');
        $items = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || str_starts_with($entry->getFilename(), '.') || $entry->isLink()) {
                continue;
            }
            if (! $entry->isDir() && ! in_array(strtolower($entry->getExtension()), self::VIDEO_EXTENSIONS, true)) {
                continue;
            }
            try {
                $file = $this->resolve($this->relative($entry->getPathname()));
                $name = $entry->getFilename();
                $items[] = [
                    'name' => $name, 'title' => $this->title($name), 'path' => $this->relative($file),
                    'kind' => is_dir($file) ? 'folder' : 'video',
                    'extension' => strtoupper($entry->getExtension()),
                    'size' => is_dir($file) ? 0 : filesize($file),
                ];
            } catch (HttpException) {
                // A removed file or an external junction must not break the catalog.
            }
        }
        usort($items, static fn (array $a, array $b): int => ($a['kind'] === $b['kind'] ? strnatcasecmp($a['title'], $b['title']) : ($a['kind'] === 'folder' ? -1 : 1)));
        $folder = $this->relative($directory);

        return [
            'folder' => $folder,
            'parent' => $folder === '' ? null : (dirname($folder) === '.' ? '' : dirname($folder)),
            'title' => $folder === '' ? 'Minha biblioteca' : $this->title(basename($directory)),
            'libraryName' => basename($this->root()), 'items' => $items,
            'folders' => count(array_filter($items, static fn ($item) => $item['kind'] === 'folder')),
            'videos' => count(array_filter($items, static fn ($item) => $item['kind'] === 'video')),
        ];
    }

    public function title(string $name): string
    {
        $name = preg_replace('/\.(mp4|m4v|mkv|webm|mov|avi|ts|m2ts|mpg|mpeg)$/i', '', $name);

        return Str::squish(str_replace(['.', '_'], ' ', $name));
    }

    public function mime(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4', 'mkv' => 'video/x-matroska', 'webm' => 'video/webm',
            'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'ts', 'm2ts' => 'video/mp2t',
            default => 'video/mpeg',
        };
    }
}
