<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_displayed_without_sidebar(): void
    {
        $response = $this->get('/opnavi/admin/login');

        $response->assertOk();
        $response->assertSee('管理者ログイン');
        $response->assertSee(route('admin.login.submit'), false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('営業ダッシュボード');
        $response->assertDontSee('ユーザー管理');
    }

    public function test_user_login_page_is_displayed_without_sidebar(): void
    {
        $response = $this->get('/opnavi/user/login');

        $response->assertOk();
        $response->assertSee('ユーザーログイン');
        $response->assertSee(route('user.login.submit'), false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('一覧画面');
    }

    public function test_invitation_login_url_depends_on_role(): void
    {
        Mail::fake();

        $this->actingAs($this->adminUser())->post('/opnavi/admin/user_management', [
            'email' => 'admin-user@illuvia-inc.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'admin',
        ]);
        $this->actingAs($this->adminUser())->post('/opnavi/admin/user_management', [
            'email' => 'appointment-user@illuvia-inc.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'appointment',
        ]);

        $admin = User::where('email', 'admin-user@illuvia-inc.com')->firstOrFail();
        $appointment = User::where('email', 'appointment-user@illuvia-inc.com')->firstOrFail();

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($admin) {
            return $mail->user->is($admin) && $mail->loginUrl === url('/opnavi/admin/login');
        });
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($appointment) {
            return $mail->user->is($appointment) && $mail->loginUrl === url('/opnavi/user/login');
        });
    }

    public function test_admin_can_login_from_admin_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@illuvia-inc.com',
            'role' => 'admin',
        ]);

        $response = $this->post('/opnavi/admin/login', [
            'email' => 'admin@illuvia-inc.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/opnavi/admin/customers');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invited_admin_is_redirected_to_initial_setup_after_login(): void
    {
        $user = User::factory()->create([
            'email' => 'invited-admin@illuvia-inc.com',
            'role' => 'admin',
            'last_name' => null,
            'first_name' => null,
            'must_change_password' => true,
        ]);

        $response = $this->post('/opnavi/admin/login', [
            'email' => 'invited-admin@illuvia-inc.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/opnavi/initial_setup');
        $this->assertAuthenticatedAs($user);
    }

    public function test_initial_setup_updates_name_and_password_then_redirects_by_role(): void
    {
        $user = User::factory()->create([
            'email' => 'invited-sales@illuvia-inc.com',
            'role' => 'sales',
            'last_name' => null,
            'first_name' => null,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->post('/opnavi/initial_setup', [
            'last_name' => '五十嵐',
            'first_name' => '真',
            'password' => 'Newpass123',
            'password_confirmation' => 'Newpass123',
        ]);

        $response->assertRedirect('/opnavi/user/customers');

        $user->refresh();
        $this->assertSame('五十嵐', $user->last_name);
        $this->assertSame('真', $user->first_name);
        $this->assertSame('五十嵐 真', $user->name);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->initial_password_expires_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Newpass123', $user->password));
    }

    public function test_expired_initial_password_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'expired-sales@illuvia-inc.com',
            'role' => 'sales',
            'last_name' => null,
            'first_name' => null,
            'must_change_password' => true,
            'initial_password_expires_at' => now()->subMinute(),
        ]);

        $response = $this->from('/opnavi/user/login')->post('/opnavi/user/login', [
            'email' => 'expired-sales@illuvia-inc.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/opnavi/user/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_initial_setup_page_is_displayed_without_sidebar(): void
    {
        $user = User::factory()->create([
            'role' => 'sales',
            'last_name' => null,
            'first_name' => null,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get('/opnavi/initial_setup');

        $response->assertOk();
        $response->assertSee('初回設定');
        $response->assertDontSee('一覧画面');
        $response->assertDontSee('営業ダッシュボード');
        $response->assertDontSee('ユーザー管理');
    }

    public function test_sidebar_shows_user_menu_with_logout_and_password_change(): void
    {
        $user = User::factory()->create([
            'name' => '管理者',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get('/opnavi/admin/customers');

        $response->assertOk();
        $response->assertSee('管理者');
        $response->assertSee('パスワード変更');
        $response->assertSee(route('password.edit'), false);
        $response->assertSee('ログアウト');
        $response->assertSee(route('logout'), false);
        $response->assertSee('data-confirm-submit="ログアウトしますか？"', false);
    }

    public function test_logout_redirects_to_role_login_page(): void
    {
        $user = User::factory()->create([
            'role' => 'sales',
        ]);

        $response = $this->actingAs($user)->post('/opnavi/logout');

        $response->assertRedirect('/opnavi/user/login');
        $this->assertGuest();
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->post('/opnavi/password/change', [
            'current_password' => 'password',
            'password' => 'Newpass123',
            'password_confirmation' => 'Newpass123',
        ]);

        $response->assertRedirect('/opnavi/admin/customers');

        $user->refresh();
        $this->assertTrue(Hash::check('Newpass123', $user->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->from('/opnavi/password/change')->post('/opnavi/password/change', [
            'current_password' => 'wrong-password',
            'password' => 'Newpass123',
            'password_confirmation' => 'Newpass123',
        ]);

        $response->assertRedirect('/opnavi/password/change');
        $response->assertSessionHasErrors('current_password');

        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_guest_is_redirected_from_initial_setup_to_login(): void
    {
        $response = $this->get('/opnavi/initial_setup');

        $response->assertRedirect('/login');

        $login = $this->get('/login');
        $login->assertRedirect('/opnavi/admin/login');
    }

    public function test_guest_user_area_redirects_to_user_login(): void
    {
        $response = $this->get('/opnavi/user/customers');

        $response->assertRedirect('/opnavi/user/login');
    }

    public function test_guest_admin_area_redirects_to_admin_login(): void
    {
        $response = $this->get('/opnavi/admin/customers');

        $response->assertRedirect('/opnavi/admin/login');
    }

    public function test_sales_can_login_from_user_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'sales@illuvia-inc.com',
            'role' => 'sales',
        ]);

        $response = $this->post('/opnavi/user/login', [
            'email' => 'sales@illuvia-inc.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/opnavi/user/customers');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_role_cannot_login_from_admin_login_page(): void
    {
        User::factory()->create([
            'email' => 'sales@illuvia-inc.com',
            'role' => 'sales',
        ]);

        $response = $this->from('/opnavi/admin/login')->post('/opnavi/admin/login', [
            'email' => 'sales@illuvia-inc.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/opnavi/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_login(): void
    {
        User::factory()->create([
            'email' => 'admin@illuvia-inc.com',
            'role' => 'admin',
        ]);

        $response = $this->from('/opnavi/admin/login')->post('/opnavi/admin/login', [
            'email' => 'admin@illuvia-inc.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/opnavi/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_initial_setup_user_is_forced_back_from_protected_pages(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'last_name' => null,
            'first_name' => null,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get('/opnavi/admin/customers');

        $response->assertRedirect('/opnavi/initial_setup');
    }

    public function test_role_cannot_access_wrong_area_directly(): void
    {
        $user = User::factory()->create([
            'role' => 'sales',
        ]);

        $response = $this->actingAs($user)->get('/opnavi/admin/customers');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
