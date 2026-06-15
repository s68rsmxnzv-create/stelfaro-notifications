<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('notifications.api_token', '');

        abort_if($expected === '', 503, 'El token interno de notificaciones no esta configurado.');
        abort_unless(hash_equals($expected, (string) $request->bearerToken()), 401, 'No autorizado.');

        return $next($request);
    }
}
