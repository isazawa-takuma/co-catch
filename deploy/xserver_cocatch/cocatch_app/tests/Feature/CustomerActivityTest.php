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

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]));
    }

    public function test_activity_registration_syncs_customer_status(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '未対応']));

        $response = $this->post('/opnavi/admin/customers/'.$customer->id.'/activities', [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'contact_person' => '山田',
            'contact_status' => '担当者',
            'status' => '商談中',
            'memo' => '商談化しました',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
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

    public function test_activity_fields_can_be_updated_and_sync_latest_customer_status(): void
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

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id.'/activities/'.$activity->id, [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'contact_person' => '佐藤',
            'contact_status' => '代表',
            'status' => '契約',
            'memo' => '更新後メモ',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を更新しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'id' => $activity->id,
            'contact_person' => '佐藤',
            'contact_status' => '代表',
            'status' => '契約',
            'memo' => '更新後メモ',
        ]);
        $customer->refresh();
        $this->assertSame('契約', $customer->status);
        $this->assertSame('更新後メモ', $customer->last_action_summary);
    }

    public function test_activities_with_same_action_time_are_shown_newest_first(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData());

        Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-27 01:02:00',
            'user_id' => $user->id,
            'status' => '連絡済み',
            'memo' => '1つ目',
        ]);
        Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-27 01:02:00',
            'user_id' => $user->id,
            'status' => '連絡済み',
            'memo' => '2つ目',
        ]);

        $this->get('/opnavi/admin/customers/'.$customer->id)
            ->assertOk()
            ->assertSeeInOrder(['2つ目', '1つ目']);
    }

    public function test_activity_can_be_deleted_and_customer_syncs_to_next_latest_activity(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '契約']));
        $olderActivity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-20 10:00:00',
            'user_id' => $user->id,
            'status' => '商談中',
            'memo' => '古い履歴',
        ]);
        $latestActivity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-21 10:00:00',
            'user_id' => $user->id,
            'status' => '契約',
            'memo' => '新しい履歴',
        ]);

        $response = $this->delete('/opnavi/admin/customers/'.$customer->id.'/activities/'.$latestActivity->id);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を削除しました');
        $this->assertDatabaseMissing('opnavi_activities', [
            'id' => $latestActivity->id,
        ]);
        $this->assertDatabaseHas('opnavi_activities', [
            'id' => $olderActivity->id,
        ]);
        $customer->refresh();
        $this->assertSame('商談中', $customer->status);
        $this->assertSame('古い履歴', $customer->last_action_summary);
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
