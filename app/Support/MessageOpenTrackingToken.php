<?php

namespace App\Support;

use App\Models\NotificationMessage;
use RuntimeException;

class MessageOpenTrackingToken
{
    public function url(NotificationMessage $message): string
    {
        $base = rtrim((string) config('notifications.tracking.public_base_url'), '/');

        return $base.'/v1/t/'.$this->token($message->id).'.png';
    }

    public function resolve(string $token): ?int
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$encodedId, $signature] = $parts;

        $padded = strtr($encodedId, '-_', '+/').str_repeat('=', (4 - strlen($encodedId) % 4) % 4);
        $decoded = base64_decode($padded, true);
        $id = $decoded !== false ? filter_var($decoded, FILTER_VALIDATE_INT) : false;

        if ($id === false || $id === null || $id <= 0) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            return null;
        }

        if (! hash_equals($this->signature((int) $id), strtolower($signature))) {
            return null;
        }

        return (int) $id;
    }

    private function token(int $messageId): string
    {
        $encodedId = rtrim(strtr(base64_encode((string) $messageId), '+/', '-_'), '=');

        return $encodedId.'.'.$this->signature($messageId);
    }

    private function signature(int $messageId): string
    {
        $key = (string) config('notifications.tracking.pixel_key');
        if ($key === '') {
            throw new RuntimeException('No se configuró la llave para el seguimiento de apertura de correos.');
        }

        return hash_hmac('sha256', implode('|', [
            'stelfaro-message-open-tracking-v1',
            (string) $messageId,
        ]), $key);
    }
}
