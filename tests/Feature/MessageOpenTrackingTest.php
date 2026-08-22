<?php

namespace Tests\Feature;

use App\Models\NotificationMessage;
use App\Support\MessageOpenTrackingToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageOpenTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['notifications.tracking.public_base_url' => 'http://localhost/api']);
    }

    public function test_loading_the_pixel_records_the_first_open_and_increments_open_count(): void
    {
        $message = $this->annexMessage();
        $url = app(MessageOpenTrackingToken::class)->url($message);
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertStringStartsWith('/api/v1/t/', $path);

        $response = $this->get($path);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        $message->refresh();
        $this->assertNotNull($message->opened_at);
        $this->assertSame(1, $message->open_count);
        $this->assertDatabaseHas('notification_events', [
            'notification_message_id' => $message->id,
            'type' => 'opened',
        ]);

        $firstOpenedAt = $message->opened_at;

        $this->get($path)->assertOk();
        $message->refresh();

        $this->assertSame(2, $message->open_count);
        $this->assertTrue($message->opened_at->equalTo($firstOpenedAt));
    }

    public function test_tampered_token_returns_the_pixel_without_recording_anything(): void
    {
        $message = $this->annexMessage();
        $url = app(MessageOpenTrackingToken::class)->url($message);
        $path = parse_url($url, PHP_URL_PATH);
        $flippedChar = str_ends_with($path, 'a.png') ? 'b' : 'a';
        $tampered = substr($path, 0, -5).$flippedChar.'.png';

        $response = $this->get($tampered);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');

        $message->refresh();
        $this->assertNull($message->opened_at);
        $this->assertSame(0, $message->open_count);
    }

    private function annexMessage(): NotificationMessage
    {
        return NotificationMessage::query()->create([
            'source_type' => 'annex',
            'source_id' => 7,
            'empresa_id' => 7,
            'recipient_email' => 'contador@example.test',
            'status' => 'sent',
            'purpose' => 'annex_delivery',
        ]);
    }
}
