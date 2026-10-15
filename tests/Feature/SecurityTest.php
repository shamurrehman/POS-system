<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/', '/pos', '/sales', '/reports', '/settings'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_login_logs_activity_and_locks_out_after_repeated_failures(): void
    {
        $user = User::factory()->create(['username' => 'ali', 'password' => 'Secret123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'ali', 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => 'ali', 'password' => 'Secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertSame(5, ActivityLog::where('action', 'auth.failed')->count());
    }

    public function test_disabled_users_cannot_log_in_and_are_kicked_out(): void
    {
        $user = User::factory()->create(['username' => 'sara', 'password' => 'Secret123', 'is_active' => false]);
        $this->post('/login', ['username' => 'sara', 'password' => 'Secret123'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->actingAs($user)->get('/pos')->assertRedirect(route('login'));
    }

    public function test_successful_login_sends_cashier_to_pos(): void
    {
        User::factory()->create(['username' => 'cash', 'password' => 'Secret123']);
        $this->post('/login', ['username' => 'cash', 'password' => 'Secret123'])->assertRedirect(route('pos'));
        $this->assertAuthenticated();
    }

    public function test_role_restrictions(): void
    {
        $cashier = User::factory()->create();
        $manager = User::factory()->role('manager')->create();
        $admin = User::factory()->role('admin')->create();

        $this->actingAs($cashier)->get('/reports')->assertForbidden();
        $this->actingAs($cashier)->get('/purchases/create')->assertForbidden();
        $this->actingAs($manager)->get('/reports')->assertOk();
        $this->actingAs($manager)->get('/users')->assertForbidden();
        $this->actingAs($manager)->get('/settings')->assertForbidden();
        $this->actingAs($admin)->get('/users')->assertOk();
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'cashier', 'is_active' => 1,
        ])->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_backup_download_rejects_path_traversal(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->get('/backups/..%2F.env')->assertNotFound();
        $this->actingAs($admin)->get('/backups/database.sqlite')->assertNotFound();
    }

    public function test_csv_export_neutralises_formulas(): void
    {
        $manager = User::factory()->role('manager')->create();
        \App\Models\Product::factory()->create(['name' => '=HYPERLINK("http://x")']);

        $csv = $this->actingAs($manager)->get('/reports/inventory?export=csv')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
