<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_links_to_user_management(): void
    {
        $response = $this->get('/opnavi/admin/customers');

        $response->assertOk();
        $response->assertSee('ユーザー管理');
        $response->assertSee(route('admin.user-management.index'), false);
    }

    public function test_user_screen_does_not_show_user_management_link(): void
    {
        Customer::create($this->customerData());

        $response = $this->get('/opnavi/user/customers');

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

        $response = $this->get('/opnavi/admin/user_management');

        $response->assertOk();
        $response->assertSee('ユーザー管理');
        $response->assertSee('追加');
        $response->assertSee('data-user-invite-modal', false);
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
}
