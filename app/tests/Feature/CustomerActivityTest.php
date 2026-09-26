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
        $salesOwner = User::factory()->create(['name' => 'テスト営業', 'role' => 'sales', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '未対応']));

        $response = $this->post('/opnavi/admin/customers/'.$customer->id.'/activities', [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'rank' => 'A',
            'contact_person' => '山田',
            'contact_status' => '担当（男）',
            'status' => 'APO',
            'sales_owner_id' => $salesOwner->id,
            'memo' => '商談化しました',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を登録しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'A',
            'contact_status' => '担当（男）',
            'status' => 'APO',
            'memo' => '商談化しました',
        ]);
        $customer->refresh();
        $this->assertSame('APO', $customer->status);
        $this->assertSame($salesOwner->id, (int) $customer->sales_owner_id);
        $this->assertSame('2026-07-20', $customer->last_action_at->format('Y-m-d'));
        $this->assertSame('商談化しました', $customer->last_action_summary);
    }

    public function test_apo_activity_registration_requires_sales_owner(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '未対応']));

        $response = $this->from('/opnavi/admin/customers/'.$customer->id)
            ->post('/opnavi/admin/customers/'.$customer->id.'/activities', [
                'action_at' => '2026-07-20 10:30:00',
                'user_id' => $user->id,
                'rank' => 'A',
                'contact_person' => '山田',
                'contact_status' => '担当（男）',
                'status' => 'APO',
                'memo' => '商談化しました',
            ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHasErrors('sales_owner_id');
        $this->assertDatabaseMissing('opnavi_activities', [
            'customer_id' => $customer->id,
            'status' => 'APO',
            'memo' => '商談化しました',
        ]);
    }

    public function test_sales_owner_can_only_be_selected_from_apo_prompt(): void
    {
        $salesOwner = User::factory()->create(['name' => 'テスト営業', 'role' => 'sales', 'is_active' => true]);
        $customer = Customer::create($this->customerData([
            'sales_owner_id' => $salesOwner->id,
        ]));

        $response = $this->get('/opnavi/admin/customers/'.$customer->id);

        $response->assertOk();
        $response->assertSee('<select name="sales_owner_id" disabled>', false);
        $response->assertSee('<select data-sales-owner-select>', false);
    }

    public function test_activity_fields_can_be_updated_and_sync_latest_customer_status(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['status' => '商談中']));
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'rank' => 'A',
            'contact_person' => '山田',
            'contact_status' => '担当（男）',
            'status' => 'コール',
            'memo' => '更新前メモ',
        ]);

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id.'/activities/'.$activity->id, [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'rank' => 'B',
            'contact_person' => '佐藤',
            'contact_status' => '社長',
            'status' => '初訪',
            'memo' => '更新後メモ',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を更新しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'id' => $activity->id,
            'rank' => 'B',
            'contact_person' => '佐藤',
            'contact_status' => '社長',
            'status' => '初訪',
            'memo' => '更新後メモ',
        ]);
        $customer->refresh();
        $this->assertSame('初訪', $customer->status);
        $this->assertSame('更新後メモ', $customer->last_action_summary);
    }

    public function test_activity_can_be_updated_to_apo_and_assign_sales_owner(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $salesOwner = User::factory()->create(['name' => 'テスト営業', 'role' => 'sales', 'is_active' => true]);
        $customer = Customer::create($this->customerData([
            'status' => 'コール',
            'sales_owner_id' => null,
        ]));
        $activity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'rank' => 'A',
            'contact_person' => '山田',
            'contact_status' => '担当（男）',
            'status' => 'コール',
            'memo' => '更新前メモ',
        ]);

        $detail = $this->get('/opnavi/admin/customers/'.$customer->id);

        $detail->assertOk();
        $detail->assertSee('id="activity-update-'.$activity->id.'"', false);
        $detail->assertSee('data-activity-form', false);
        $detail->assertSee('data-activity-status-select', false);
        $detail->assertSee('data-activity-sales-owner-input', false);

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id.'/activities/'.$activity->id, [
            'action_at' => '2026-07-20 10:30:00',
            'user_id' => $user->id,
            'rank' => 'A',
            'contact_person' => '山田',
            'contact_status' => '担当（男）',
            'status' => 'APO',
            'sales_owner_id' => $salesOwner->id,
            'memo' => 'APOへ更新しました',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHas('status', '履歴を更新しました');
        $this->assertDatabaseHas('opnavi_activities', [
            'id' => $activity->id,
            'status' => 'APO',
            'memo' => 'APOへ更新しました',
        ]);
        $customer->refresh();
        $this->assertSame('APO', $customer->status);
        $this->assertSame($salesOwner->id, (int) $customer->sales_owner_id);
    }

    public function test_activities_with_same_action_time_are_shown_newest_first(): void
    {
        $user = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData());

        Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-27 01:02:00',
            'user_id' => $user->id,
            'rank' => 'A',
            'status' => 'コール',
            'memo' => '1つ目',
        ]);
        Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-27 01:02:00',
            'user_id' => $user->id,
            'rank' => 'B',
            'status' => 'コール',
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
            'rank' => 'A',
            'status' => 'コール',
            'memo' => '古い履歴',
        ]);
        $latestActivity = Activity::create([
            'customer_id' => $customer->id,
            'action_at' => '2026-07-21 10:00:00',
            'user_id' => $user->id,
            'rank' => 'B',
            'status' => 'APO',
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
        $this->assertSame('コール', $customer->status);
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
