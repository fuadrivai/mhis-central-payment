<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallbackApiKeySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_callback_api_key_settings(): void
    {
        $this->get(route('settings.callback-api-key.index'))
            ->assertRedirect(route('login'));

        $this->post(route('settings.callback-api-key.store'))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_admin_cannot_manage_callback_api_key(): void
    {
        $user = User::factory()->create([
            'role' => 'finance_pusat',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('settings.callback-api-key.index'))
            ->assertForbidden();

        $this->post(route('settings.callback-api-key.store'))
            ->assertForbidden();

        $this->assertDatabaseHas('banks', [
            'code' => 'mandiri',
            'callback_api_key_hash' => null,
        ]);
    }

    public function test_super_admin_can_see_when_the_callback_api_key_has_not_been_generated(): void
    {
        $user = $this->createSuperAdmin();

        $this->actingAs($user)
            ->get(route('settings.callback-api-key.index'))
            ->assertOk()
            ->assertSee('Belum dibuat')
            ->assertDontSee('Terkonfigurasi');
    }

    public function test_super_admin_can_generate_a_key_that_is_shown_once_and_stored_as_a_hash(): void
    {
        $user = $this->createSuperAdmin();
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();

        $response = $this->actingAs($user)->post(route('settings.callback-api-key.store'));
        $apiKey = session('callback_api_key');

        $response->assertRedirect(route('settings.callback-api-key.index'));
        $response->assertSessionHas('status');
        $this->assertIsString($apiKey);
        $this->assertSame(64, strlen($apiKey));
        $this->assertSame(hash('sha256', $apiKey), $bank->fresh()->callback_api_key_hash);
        $this->assertNotSame($apiKey, $bank->fresh()->callback_api_key_hash);

        $this->get(route('settings.callback-api-key.index'))
            ->assertOk()
            ->assertSee($apiKey)
            ->assertSee('Terkonfigurasi');

        $this->get(route('settings.callback-api-key.index'))
            ->assertOk()
            ->assertDontSee($apiKey);
    }

    public function test_generating_a_new_key_replaces_the_previous_key_hash(): void
    {
        $user = $this->createSuperAdmin();
        $bank = Bank::query()->where('code', 'mandiri')->firstOrFail();

        $this->actingAs($user)->post(route('settings.callback-api-key.store'));
        $previousHash = $bank->fresh()->callback_api_key_hash;

        $response = $this->from(route('settings.callback-api-key.index'))
            ->actingAs($user)
            ->post(route('settings.callback-api-key.store'));
        $newApiKey = session('callback_api_key');

        $response->assertRedirect(route('settings.callback-api-key.index'));
        $this->assertIsString($newApiKey);
        $this->assertNotSame($previousHash, hash('sha256', $newApiKey));
        $this->assertSame(hash('sha256', $newApiKey), $bank->fresh()->callback_api_key_hash);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
