<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_detail_shows_and_saves_next_action_alert_checkbox(): void
    {
        $this->actingAs($this->adminUser());
        $customer = Customer::create($this->customerData([
            'next_action_at' => '2026-08-20 10:00:00',
        ]));

        $response = $this->get('/opnavi/admin/customers/'.$customer->id);

        $response->assertOk();
        $response->assertSee('アラート');
        $response->assertSee('name="next_action_alert_enabled"', false);

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id, [
            'next_action_at' => '2026-08-20T10:00',
            'next_action_alert_enabled' => '1',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $customer->id,
            'next_action_alert_enabled' => true,
        ]);
    }

    public function test_customer_alert_endpoint_returns_alerts_five_minutes_before_next_action(): void
    {
        $this->actingAs($this->adminUser());
        $this->travelTo('2026-08-20 09:55:00');

        $customer = Customer::create($this->customerData([
            'business_name' => '通知対象事業者',
            'next_action_at' => '2026-08-20 10:00:00',
            'next_action_alert_enabled' => true,
        ]));
        Customer::create($this->customerData([
            'business_name' => '通知OFF事業者',
            'next_action_at' => '2026-08-20 10:00:00',
            'next_action_alert_enabled' => false,
            'address' => '埼玉県さいたま市2-2-2',
        ]));
        Customer::create($this->customerData([
            'business_name' => '時間外事業者',
            'next_action_at' => '2026-08-20 10:10:00',
            'next_action_alert_enabled' => true,
            'address' => '埼玉県さいたま市3-3-3',
        ]));
        Customer::create($this->customerData([
            'business_name' => '古いアラート事業者',
            'next_action_at' => '2026-08-20 09:44:00',
            'next_action_alert_enabled' => true,
            'address' => '埼玉県さいたま市4-4-4',
        ]));

        $response = $this->getJson('/opnavi/customer_alerts');

        $response->assertOk();
        $response->assertJsonFragment([
            'business_name' => '通知対象事業者',
            'message' => '通知対象事業者の次回アクション日時（2026/08/20 10:00）が近づいてきました',
            'detail_url' => 'http://localhost:8080/opnavi/admin/customers/'.$customer->id,
        ]);
        $response->assertJsonMissing(['business_name' => '通知OFF事業者']);
        $response->assertJsonMissing(['business_name' => '時間外事業者']);
        $response->assertJsonMissing(['business_name' => '古いアラート事業者']);
    }

    public function test_customer_alert_endpoint_keeps_recently_due_alerts_available(): void
    {
        $this->actingAs($this->adminUser());
        $this->travelTo('2026-08-20 10:01:00');

        Customer::create($this->customerData([
            'business_name' => '予定時刻直後の通知対象',
            'next_action_at' => '2026-08-20 10:00:00',
            'next_action_alert_enabled' => true,
        ]));

        $response = $this->getJson('/opnavi/customer_alerts');

        $response->assertOk();
        $response->assertJsonFragment([
            'business_name' => '予定時刻直後の通知対象',
            'message' => '予定時刻直後の通知対象の次回アクション日時（2026/08/20 10:00）が近づいてきました',
        ]);
    }

    public function test_user_customer_alert_endpoint_returns_only_owned_customers(): void
    {
        $user = $this->salesUser();
        $otherUser = $this->salesUser();
        $this->actingAs($user);
        $this->travelTo('2026-08-20 09:55:00');

        $customer = Customer::create($this->customerData([
            'business_name' => '自分の担当事業者',
            'owner_id' => $user->id,
            'next_action_at' => '2026-08-20 10:00:00',
            'next_action_alert_enabled' => true,
        ]));
        Customer::create($this->customerData([
            'business_name' => '他人の担当事業者',
            'owner_id' => $otherUser->id,
            'next_action_at' => '2026-08-20 10:00:00',
            'next_action_alert_enabled' => true,
            'address' => '埼玉県さいたま市2-2-2',
        ]));

        $response = $this->getJson('/opnavi/customer_alerts');

        $response->assertOk();
        $response->assertJsonFragment([
            'business_name' => '自分の担当事業者',
            'message' => '自分の担当事業者の次回アクション日時（2026/08/20 10:00）が近づいてきました',
            'detail_url' => 'http://localhost:8080/opnavi/user/customers/'.$customer->id,
        ]);
        $response->assertJsonMissing(['business_name' => '他人の担当事業者']);
    }

    public function test_user_customer_update_can_save_next_action_alert_enabled(): void
    {
        $user = $this->salesUser();
        $this->actingAs($user);
        $customer = Customer::create($this->customerData(['owner_id' => $user->id]));

        $response = $this->patch('/opnavi/user/customers/'.$customer->id, [
            'next_action_at' => '2026-08-20T10:00',
            'next_action_alert_enabled' => '1',
            'sales_memo' => 'アラートを設定',
        ]);

        $response->assertRedirect('/opnavi/user/customers/'.$customer->id);
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $customer->id,
            'next_action_alert_enabled' => true,
            'sales_memo' => 'アラートを設定',
        ]);
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
