<?php

namespace Tests\Feature;

use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertDontSee('一覧画面');
    }

    public function test_invitation_login_url_depends_on_role(): void
    {
        Mail::fake();

        $this->post('/opnavi/admin/user_management', [
            'email' => 'admin-user@illuvia-inc.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'admin',
        ]);
        $this->post('/opnavi/admin/user_management', [
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
}
