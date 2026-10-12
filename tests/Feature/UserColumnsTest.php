<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_supports_new_columns(): void
    {
        $creator = User::factory()->create();

        $loginAt = Carbon::now();

        $user = User::create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'fullname' => 'Johnathon Doe',
            'email' => 'john@example.com',
            'image' => 'avatars/john.png',
            'password' => 'secret123',
            'created_by' => $creator->id,
            'status' => 'active',
            'last_device_token' => 'fcm-device-token-12345',
            'last_login_at' => $loginAt,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'johndoe',
            'fullname' => 'Johnathon Doe',
            'email' => 'john@example.com',
            'image' => 'avatars/john.png',
            'created_by' => $creator->id,
            'status' => 'active',
            'last_device_token' => 'fcm-device-token-12345',
        ]);

        $this->assertInstanceOf(Carbon::class, $user->fresh()->last_login_at);
        $this->assertEquals($creator->id, $user->creator->id);
    }

    public function test_user_factory_generates_valid_defaults(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->username);
        $this->assertNotNull($user->fullname);
        $this->assertEquals('active', $user->status);
    }

    public function test_admin_user_seeder_creates_admin_user(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'username' => 'admin',
            'email' => 'admin@example.com',
            'status' => 'active',
        ]);
    }
}
