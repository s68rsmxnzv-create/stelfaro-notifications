<?php

namespace App\Http\Controllers;

use App\Models\NotificationMessage;
use App\Support\MessageOpenTrackingToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MessageOpenTrackingController extends Controller
{
    private const TRANSPARENT_PIXEL_PNG = "\x89PNG\x0D\x0A\x1A\x0A\x00\x00\x00\x0DIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\x0AIDATx\x9Cc\x00\x01\x00\x00\x05\x00\x01\x0D\x0A\x2D\xB4\x00\x00\x00\x00IEND\xAEB\x60\x82";

    public function pixel(string $token, Request $request, MessageOpenTrackingToken $tokens): Response
    {
        $messageId = $tokens->resolve($token);

        if ($messageId !== null) {
            $message = NotificationMessage::query()->find($messageId);

            if ($message) {
                $message->forceFill([
                    'opened_at' => $message->opened_at ?? now(),
                    'open_count' => $message->open_count + 1,
                ])->save();

                $message->recordEvent('opened', [
                    'user_agent' => (string) $request->userAgent(),
                    'ip' => (string) $request->ip(),
                ]);
            }
        }

        return response(self::TRANSPARENT_PIXEL_PNG, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen(self::TRANSPARENT_PIXEL_PNG),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
