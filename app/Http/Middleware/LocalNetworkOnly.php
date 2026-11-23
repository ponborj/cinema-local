<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final class LocalNetworkOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $private = ['127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '::1'];
        $ip = preg_replace('/^::ffff:/i', '', $request->ip() ?? '');
        abort_unless(IpUtils::checkIp($ip, $private), 403, 'Acesso permitido somente pela rede local.');
        $host = $request->getHost();
        $allowedHost = in_array($host, config('media.allowed_hosts'), true) || (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && IpUtils::checkIp($host, $private));
        abort_unless($allowedHost, 403, 'Use o endereço local do computador.');
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; media-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
        if (! $response->headers->has('Content-Type') || ! str_starts_with($response->headers->get('Content-Type'), 'video/')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
