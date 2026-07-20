<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_registration_syncs_customer_status(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '未対応']));

        $response = $this->post('/opnavi/customers/'.$customer->id.'/activities', [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'contact_person' => '山田',
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '商談化しました',
        ]);

        $response->assertRedirect('/opnavi/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を登録しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '商談化しました',
        ]);
        $customer->refresh();
        $this->assertSame('商談中', $customer->status);
        $this->assertSame('2026-07-20', $customer->last_action_at->format('Y-m-d'));
        $this->assertSame('商談化しました', $customer->last_action_summary);
    }

    public function test_activity_memo_can_be_updated_without_changing_other_fields(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '商談中']));
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'contact_person' => '山田',
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '更新前メモ',
        ]);

        $response = $this->patch('/opnavi/customers/'.$customer->id.'/activities/'.$activity->id, [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'contact_person' => '山田',
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '更新後メモ',
        ]);

        $response->assertRedirect('/opnavi/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を更新しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'id' => $activity->id,
            'contact_person' => '山田',
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '更新後メモ',
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
}
