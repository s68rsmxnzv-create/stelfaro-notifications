<?php

namespace Tests\Feature;

use App\Models\NotificationSenderAlias;
use Database\Seeders\PlatformSenderAliasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSenderAliasSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_account_activation_alias(): void
    {
        $this->seed(PlatformSenderAliasSeeder::class);

        $alias = NotificationSenderAlias::query()->where('purpose', 'platform_account_activation')->first();

        $this->assertNotNull($alias);
        $this->assertSame('activaciondecuenta@stelfaro.com', $alias->from_email);
        $this->assertSame('global', $alias->scope_type);
        $this->assertTrue($alias->is_active);
    }

    public function test_it_is_idempotent_and_keeps_panel_overrides(): void
    {
        $this->seed(PlatformSenderAliasSeeder::class);

        NotificationSenderAlias::query()->where('purpose', 'platform_account_activation')
            ->update(['from_email' => 'otro@stelfaro.com']);

        $this->seed(PlatformSenderAliasSeeder::class);

        $this->assertSame(1, NotificationSenderAlias::query()->where('purpose', 'platform_account_activation')->count());
        $this->assertSame(
            'otro@stelfaro.com',
            NotificationSenderAlias::query()->where('purpose', 'platform_account_activation')->value('from_email'),
        );
    }
}
