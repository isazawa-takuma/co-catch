<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Mail\UserInvitationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_links_to_user_management(): void
    {
        $response = $this->actingAs($this->adminUser())->get('/opnavi/admin/customers');

        $response->assertOk();
        $response->assertSee('ユーザー管理');
        $response->assertSee(route('admin.user-management.index'), false);
    }

    public function test_user_screen_does_not_show_user_management_link(): void
    {
        Customer::create($this->customerData());

        $response = $this->actingAs($this->salesUser())->get('/opnavi/user/customers');

        $response->assertOk();
        $response->assertDontSee('ユーザー管理');
    }

    public function test_user_management_page_lists_users_and_add_button(): void
    {
        User::factory()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => '一般ユーザー',
            'email' => 'user@example.com',
            'role' => 'member',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser())->get('/opnavi/admin/user_management');

        $response->assertOk();
        $response->assertSee('ユーザー管理');
        $response->assertSee('追加');
        $response->assertSee('data-user-invite-modal', false);
        $response->assertSee(route('admin.user-management.store'), false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="initial_password"', false);
        $response->assertSee('data-user-invite-password', false);
        $response->assertDontSee('data-user-invite-password readonly', false);
        $response->assertSee('name="role"', false);
        $response->assertSee('value="appointment"', false);
        $response->assertSee('アポイント');
        $response->assertSee('value="sales"', false);
        $response->assertSee('営業');
        $response->assertSee('value="admin"', false);
        $response->assertSee('戻る');
        $response->assertSee('送信');
        $response->assertSee('管理者');
        $response->assertSee('admin@example.com');
        $response->assertSee('一般ユーザー');
        $response->assertSee('user@example.com');
        $response->assertSee('有効');
        $response->assertSee('停止中');
    }

    public function test_user_invitation_creates_user_and_sends_mail(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->adminUser())->post('/opnavi/admin/user_management', [
            'email' => 'new-user@illuvia-inc.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'sales',
        ]);

        $response->assertRedirect('/opnavi/admin/user_management');
        $response->assertSessionHas('status', 'ユーザーを追加し、招待メールを送信しました。');

        $user = User::where('email', 'new-user@illuvia-inc.com')->firstOrFail();
        $this->assertSame('new-user@illuvia-inc.com', $user->name);
        $this->assertSame('sales', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->initial_password_expires_at);
        $this->assertTrue($user->initial_password_expires_at->between(now()->addDays(6)->addHours(23), now()->addDays(7)->addMinute()));
        $this->assertTrue(Hash::check('Passw0rd123', $user->password));

        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use ($user) {
            return $mail->hasTo('new-user@illuvia-inc.com')
                && $mail->user->is($user)
                && $mail->initialPassword === 'Passw0rd123'
                && $mail->loginUrl === url('/opnavi/user/login');
        });
    }

    public function test_user_invitation_requires_illuvia_email_domain(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->adminUser())->from('/opnavi/admin/user_management')->post('/opnavi/admin/user_management', [
            'email' => 'new-user@example.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'sales',
        ]);

        $response->assertRedirect('/opnavi/admin/user_management');
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.com']);
        Mail::assertNothingSent();

        $page = $this->actingAs($this->adminUser())->get('/opnavi/admin/user_management');

        $page->assertOk();
        $page->assertSee('data-user-invite-modal', false);
        $page->assertDontSee('data-user-invite-modal hidden', false);
    }

    public function test_user_invitation_rolls_back_user_when_mail_sending_fails(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with('failed-user@illuvia-inc.com')
            ->andThrow(new RuntimeException('mail failed'));

        $response = $this->actingAs($this->adminUser())->post('/opnavi/admin/user_management', [
            'email' => 'failed-user@illuvia-inc.com',
            'initial_password' => 'Passw0rd123',
            'role' => 'admin',
        ]);

        $response->assertRedirect('/opnavi/admin/user_management');
        $response->assertSessionHas('error', '招待メールの送信に失敗しました。メール設定を確認してから再度お試しください。');
        $response->assertSessionHas('open_user_invite', true);
        $this->assertDatabaseMissing('users', ['email' => 'failed-user@illuvia-inc.com']);
    }

    private function customerData(array $overrides = []): array
    {
        return array_merge([
            'registered_at' => '2026-07-20',
            'business_name' => 'サンプル事業者',
            'prefecture' => '埼玉県',
            'region' => '埼玉県',
            'area_name' => 'さいたま店',
            'address' => '埼玉県さいたま市サンプル1-2-3',
            'experience_title' => '陶芸体験',
            'domestic_otas' => 'じゃらん',
            'ota_count' => 1,
            'monthly_open_days' => 20,
            'request_booking_status' => 'あり',
            'status' => '未対応',
        ], $overrides);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function salesUser(): User
    {
        return User::factory()->create([
            'role' => 'sales',
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }
}
