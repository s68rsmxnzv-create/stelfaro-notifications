<?php

namespace App\Support\Core;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CoreApiClient
{
    public function dtePdf(int $documentId): CoreArtifact
    {
        return $this->artifact("dte/drafts/{$documentId}/artifacts/pdf", "dte-{$documentId}.pdf");
    }

    public function dteClientJson(int $documentId): CoreArtifact
    {
        return $this->artifact("dte/drafts/{$documentId}/artifacts/client-json", "dte-{$documentId}.json");
    }

    private function artifact(string $path, string $fallbackFilename): CoreArtifact
    {
        $url = $this->url($path);
        $response = $this->request()->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Core DTE respondio {$response->status()} al solicitar {$path}.");
        }

        return new CoreArtifact(
            content: $response->body(),
            contentType: $response->header('Content-Type') ?: 'application/octet-stream',
            filename: $this->filenameFromDisposition($response->header('Content-Disposition'), $fallbackFilename),
            sourceUrl: $url,
        );
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('notifications.core.timeout', 20))->accept('*/*');
        $token = (string) config('notifications.core.token', '');

        return $token === '' ? $request : $request->withToken($token);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('notifications.core.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function filenameFromDisposition(?string $disposition, string $fallback): string
    {
        if (! $disposition) {
            return $fallback;
        }

        if (preg_match('/filename="([^"]+)"/', $disposition, $matches) === 1) {
            return basename($matches[1]);
        }

        $filename = Str::after($disposition, 'filename=');

        return $filename !== $disposition ? basename(trim($filename, " \t\n\r\0\x0B\"'")) : $fallback;
    }
}
