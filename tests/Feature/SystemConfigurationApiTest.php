<?php

namespace Tests\Feature;

use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemConfigurationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_get_returns_401_when_user_is_unauthenticated(): void
    {
        $response = $this->getJson('/api/system-configuration');

        $response->assertUnauthorized();
    }

    public function test_update_returns_401_when_user_is_unauthenticated(): void
    {
        $response = $this->putJson('/api/system-configuration', [
            'app_name' => 'Unauthorized Change',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('tkt_system_configurations', 0);
    }

    public function test_authenticated_user_receives_default_configuration(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/system-configuration');

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'Velzon Support Portal')
            ->assertJsonPath('data.footer_copyright_text', '{year} © {appName}.')
            ->assertJsonPath('data.footer_credit_text', 'Design & Develop by {companyName}')
            ->assertJsonPath('data.primary_color', '#405189')
            ->assertJsonPath('data.maintenance_mode', false);

        $this->assertDatabaseHas('tkt_system_configurations', [
            'configuration_key' => 'default',
            'app_name' => 'Velzon Support Portal',
        ]);
    }

    public function test_authenticated_user_can_update_system_configuration(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson('/api/system-configuration', [
            ...$this->validConfiguration(),
            'id' => 99,
            'configuration_key' => 'unexpected',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'System configuration updated successfully')
            ->assertJsonPath('data.app_name', 'Acme Support')
            ->assertJsonPath('data.footer_credit_text', 'Managed by {companyName}')
            ->assertJsonPath('data.primary_color', '#0AB39C')
            ->assertJsonPath('data.maintenance_mode', true);

        $this->assertDatabaseHas('tkt_system_configurations', [
            'configuration_key' => 'default',
            'app_name' => 'Acme Support',
            'footer_copyright_text' => '© {year} {appName}',
            'footer_credit_text' => 'Managed by {companyName}',
            'primary_color' => '#0AB39C',
            'maintenance_mode' => true,
        ]);
        $this->assertDatabaseCount('tkt_system_configurations', 1);
    }

    public function test_partial_update_preserves_fields_that_were_not_submitted(): void
    {
        Sanctum::actingAs(User::factory()->create());
        SystemConfiguration::query()->create([
            'configuration_key' => 'default',
            'app_name' => 'Keep This Name',
        ]);

        $response = $this->putJson('/api/system-configuration', [
            'primary_color' => '#F06548',
            'preset_color' => 'crimson',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'Keep This Name')
            ->assertJsonPath('data.primary_color', '#F06548');

        $this->assertDatabaseHas('tkt_system_configurations', [
            'app_name' => 'Keep This Name',
            'primary_color' => '#F06548',
        ]);
    }

    public function test_update_returns_422_for_invalid_configuration(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->putJson('/api/system-configuration', [
            'primary_color' => 'not-a-color',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color'])
            ->assertJsonPath('errors.primary_color.0', 'The primary color field format is invalid.');

        $this->assertDatabaseCount('tkt_system_configurations', 0);
    }

    public function test_authenticated_user_can_update_system_logo(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $dataUriLogo = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->putJson('/api/system-configuration', [
            'system_logo' => $dataUriLogo,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.system_logo', $dataUriLogo);

        $this->assertDatabaseHas('tkt_system_configurations', [
            'configuration_key' => 'default',
            'system_logo' => $dataUriLogo,
        ]);
    }

    /**
     * @return array<string, bool|string>
     */
    private function validConfiguration(): array
    {
        return [
            'app_name' => 'Acme Support',
            'company_name' => 'Acme Inc.',
            'support_email' => 'support@acme.test',
            'hotline' => '+63 2 8123 4567',
            'timezone' => 'Asia/Singapore',
            'date_format' => 'DD/MM/YYYY',
            'maintenance_mode' => true,
            'footer_copyright_text' => '© {year} {appName}',
            'footer_credit_text' => 'Managed by {companyName}',
            'preset_color' => 'custom',
            'primary_color' => '#0AB39C',
            'button_radius' => 'pill',
            'button_weight' => '700',
            'button_hover_effect' => 'glow',
            'input_radius' => 'subtle',
            'input_border_width' => '2px',
            'input_style' => 'subtle',
            'input_focus_ring' => 'minimal',
            'checkbox_radius' => 'circle',
            'checkbox_size' => 'large',
            'checkbox_border_width' => '2px',
        ];
    }
}
