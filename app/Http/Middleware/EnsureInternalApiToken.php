<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = trim((string) $request->bearerToken());
        $matchedToken = $this->matchedToken($provided);

        abort_if($this->configuredTokens() === [], 503, 'El token interno de notificaciones no esta configurado.');
        abort_unless($provided !== '' && $matchedToken !== null, 401, 'No autorizado.');

        $request->attributes->set('internal_api_client', $matchedToken['client']);
        $request->attributes->set('internal_api_token_fingerprint', substr($matchedToken['token_hash'], 0, 16));

        return $next($request);
    }

    /**
     * @return array{client: string, token_hash: string}|null
     */
    private function matchedToken(string $provided): ?array
    {
        $providedHash = hash('sha256', $provided);

        foreach ($this->configuredTokens() as $token) {
            if (hash_equals($token['token_hash'], $providedHash)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{client: string, token_hash: string}>
     */
    private function configuredTokens(): array
    {
        $tokens = config('notifications.internal_tokens', []);
        $normalized = [];

        if (is_array($tokens)) {
            foreach ($tokens as $token) {
                if (
                    is_array($token)
                    && is_string($token['client'] ?? null)
                    && is_string($token['token_hash'] ?? null)
                    && preg_match('/^[a-f0-9]{64}$/', $token['token_hash']) === 1
                ) {
                    $normalized[] = [
                        'client' => $token['client'],
                        'token_hash' => strtolower($token['token_hash']),
                    ];
                }
            }
        }

        return $normalized;
    }
}
