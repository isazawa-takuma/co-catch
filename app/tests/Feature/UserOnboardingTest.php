<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'admin' => ['admin', '/opnavi/admin/login', '/opnavi/admin/customers'],
            'sales' => ['sales', '/opnavi/user/login', '/opnavi/user/customers'],
            'appointment' => ['appointment', '/opnavi/user/login', '/opnavi/user/customers'],
        ];
    }

    /** @dataProvider roles */
    public function test_user_can_complete_onboarding_without_a_mail_server(string $role, string $loginUrl, string $homeUrl): void
    {
        config(['mail.default' => 'unconfigured-mailer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $email = $role.'-onboarding@illuvia-inc.com';

        $this->actingAs($admin)->post('/opnavi/admin/user_management', [
            'email' => $email,
            'initial_password' => 'Temporary123',
            'role' => $role,
        ])->assertSessionHasNoErrors()->assertSessionHas('user_credentials');

        $credentials = Crypt::decrypt(session('user_credentials'));
        $this->assertSame(url($loginUrl), $credentials['login_url']);
        $this->get('/opnavi/admin/user_management')->assertSee('Temporary123');
        $this->post('/opnavi/logout');

        $this->post($loginUrl, [
            'email' => $credentials['email'],
            'password' => $credentials['initial_password'],
        ])->assertRedirect('/opnavi/initial_setup');
        $this->get($homeUrl)->assertRedirect('/opnavi/initial_setup');
        $this->get('/opnavi/initial_setup')->assertOk()->assertSee('新しいパスワード');

        $this->post('/opnavi/initial_setup', [
            'last_name' => '確認',
            'first_name' => '太郎',
            'password' => 'Personal456',
            'password_confirmation' => 'Personal456',
        ])->assertRedirect($homeUrl);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->initial_password_expires_at);
        $this->assertFalse(Hash::check('Temporary123', $user->password));
        $this->get($homeUrl)->assertOk();
        $this->post('/opnavi/logout');

        $this->post($loginUrl, ['email' => $email, 'password' => 'Temporary123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post($loginUrl, ['email' => $email, 'password' => 'Personal456'])->assertRedirect($homeUrl);
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_password_can_be_reissued_and_requires_setup_again(): void
    {
        config(['mail.default' => 'unconfigured-mailer']);
        $user = User::factory()->create([
            'role' => 'sales',
            'password' => Hash::make('Expired123'),
            'must_change_password' => true,
            'initial_password_expires_at' => now()->subSecond(),
        ]);
        $this->post('/opnavi/user/login', ['email' => $user->email, 'password' => 'Expired123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.user-management.reissue', $user))
            ->assertSessionHas('user_credentials');
        $credentials = Crypt::decrypt(session('user_credentials'));
        $this->post('/opnavi/logout');

        $this->post('/opnavi/user/login', ['email' => $user->email, 'password' => $credentials['initial_password']])
            ->assertRedirect('/opnavi/initial_setup');
        $this->get('/opnavi/user/customers')->assertRedirect('/opnavi/initial_setup');
        $this->assertTrue($user->fresh()->initial_password_expires_at->isFuture());
    }

    public function test_non_admin_cannot_add_users_reissue_passwords_or_view_credentials(): void
    {
        $user = User::factory()->create(['role' => 'sales']);
        $target = User::factory()->create(['role' => 'appointment']);
        $originalPassword = $target->password;

        $this->actingAs($user)->post('/opnavi/admin/user_management', [
            'email' => 'forbidden@illuvia-inc.com',
            'initial_password' => 'Temporary123',
            'role' => 'admin',
        ])->assertRedirect('/login');
        $this->assertDatabaseMissing('users', ['email' => 'forbidden@illuvia-inc.com']);

        $this->actingAs($user)->post(route('admin.user-management.reissue', $target))->assertRedirect('/login');
        $this->assertSame($originalPassword, $target->fresh()->password);
        $this->actingAs($user)->get('/opnavi/admin/user_management')->assertRedirect('/login');
        $this->get('/opnavi/admin/user_management')->assertRedirect('/opnavi/admin/login');
    }

    public function test_duplicate_email_and_invalid_passwords_do_not_create_users_or_flash_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'existing@illuvia-inc.com']);
        $this->actingAs($admin)->post('/opnavi/admin/user_management', [
            'email' => $admin->email,
            'initial_password' => 'Temporary123',
            'role' => 'sales',
        ])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.initial_password');

        foreach (['short1', 'onlyletters', '1234567890'] as $password) {
            $this->post('/opnavi/admin/user_management', [
                'email' => 'invalid@illuvia-inc.com',
                'initial_password' => $password,
                'role' => 'sales',
            ])->assertSessionHasErrors('initial_password')
                ->assertSessionMissing('_old_input.initial_password')
                ->assertSessionMissing('user_credentials');
        }

        $this->assertDatabaseCount('users', 1);
    }
}
