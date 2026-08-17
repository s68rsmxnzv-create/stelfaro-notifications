<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiClient
{
    public function handle(Request $request, Closure $next, string ...$allowedClients): Response
    {
        $client = $request->attributes->get('internal_api_client');

        abort_unless(is_string($client) && in_array($client, $allowedClients, true), 403, 'Este cliente interno no tiene acceso a esta operacion.');

        return $next($request);
    }
}
