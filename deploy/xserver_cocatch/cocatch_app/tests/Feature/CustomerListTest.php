<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerListTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_can_be_filtered_by_region_and_owner(): void
    {
        $owner = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $otherOwner = User::factory()->create(['name' => '荒', 'is_active' => true]);

        Customer::create($this->customerData([
            'business_name' => '対象事業者',
            'region' => '埼玉県',
            'owner_id' => $owner->id,
        ]));
        Customer::create($this->customerData([
            'business_name' => '対象外事業者',
            'region' => '神奈川県',
            'owner_id' => $otherOwner->id,
        ]));

        $response = $this->get('/opnavi/admin/customers?region=埼玉県&owner_id='.$owner->id);

        $response->assertOk();
        $response->assertSee('対象事業者');
        $response->assertDontSee('対象外事業者');
    }

    public function test_inline_owner_update_returns_to_customer_list(): void
    {
        $owner = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData(['owner_id' => null]));
        $redirectTo = url('/opnavi/admin/customers?region=埼玉県&owner_id='.$owner->id);

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id, [
            'owner_id' => $owner->id,
            'redirect_to' => $redirectTo,
        ]);

        $response->assertRedirect($redirectTo);
        $response->assertSessionHas('status', '保存しました');
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $customer->id,
            'owner_id' => $owner->id,
        ]);
    }

    public function test_invalid_customer_update_does_not_show_success_status(): void
    {
        $customer = Customer::create($this->customerData(['website_url' => 'https://example.com']));

        $response = $this->from('/opnavi/admin/customers/'.$customer->id)->patch('/opnavi/admin/customers/'.$customer->id, [
            'website_url' => 'invalid-url',
        ]);

        $response->assertRedirect('/opnavi/admin/customers/'.$customer->id);
        $response->assertSessionHasErrors('website_url');
        $response->assertSessionMissing('status');
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $customer->id,
            'website_url' => 'https://example.com',
        ]);
    }

    public function test_detail_navigation_follows_current_list_sort_order(): void
    {
        $first = Customer::create($this->customerData([
            'business_name' => '先頭顧客',
            'registered_at' => '2026-07-20',
        ]));
        $second = Customer::create($this->customerData([
            'business_name' => '次の顧客',
            'registered_at' => '2026-07-19',
            'address' => '埼玉県さいたま市2-2-2',
        ]));
        $outOfOrderById = Customer::create($this->customerData([
            'business_name' => 'ID順では次だが一覧順ではない顧客',
            'registered_at' => '2026-07-18',
            'address' => '埼玉県さいたま市3-3-3',
        ]));

        $response = $this->get('/opnavi/admin/customers/'.$first->id.'?sort_by=registered_at&sort_order=desc');

        $response->assertOk();
        $response->assertSee(route('customers.show', [
            'customer' => $second,
            'sort_by' => 'registered_at',
            'sort_order' => 'desc',
        ]));
        $response->assertDontSee(route('customers.show', [
            'customer' => $outOfOrderById,
            'sort_by' => 'registered_at',
            'sort_order' => 'desc',
        ]));
    }

    public function test_selected_customers_owner_can_be_bulk_updated(): void
    {
        $owner = User::factory()->create(['name' => '荒', 'is_active' => true]);
        $selectedCustomer = Customer::create($this->customerData(['business_name' => '選択顧客']));
        $otherCustomer = Customer::create($this->customerData(['business_name' => '未選択顧客', 'address' => '埼玉県川口市1-2-3']));
        $redirectTo = url('/opnavi/admin/customers?per_page=25');

        $response = $this->patch('/opnavi/admin/customers/bulk-owner', [
            'customer_ids' => [$selectedCustomer->id],
            'owner_id' => $owner->id,
            'redirect_to' => $redirectTo,
        ]);

        $response->assertRedirect($redirectTo);
        $response->assertSessionHas('status', '1件の担当者を荒に更新しました');
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $selectedCustomer->id,
            'owner_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('opnavi_customers', [
            'id' => $otherCustomer->id,
            'owner_id' => null,
        ]);
    }

    public function test_bulk_owner_update_requires_selected_customers(): void
    {
        $owner = User::factory()->create(['name' => '荒', 'is_active' => true]);

        $response = $this->from('/opnavi/admin/customers')->patch('/opnavi/admin/customers/bulk-owner', [
            'owner_id' => $owner->id,
        ]);

        $response->assertRedirect('/opnavi/admin/customers');
        $response->assertSessionHasErrors('customer_ids');
    }

    public function test_deleted_customer_detail_redirects_to_list_with_error(): void
    {
        $customer = Customer::create($this->customerData());
        $customer->delete();

        $response = $this->get('/opnavi/admin/customers/'.$customer->id);

        $response->assertRedirect('/opnavi/admin/customers');
        $response->assertSessionHas('error', '対象の顧客が見つかりません。別タブで削除された可能性があります。一覧を再読み込みしてください。');
    }

    public function test_deleted_customer_update_redirects_to_list_with_error(): void
    {
        $customer = Customer::create($this->customerData());
        $customer->delete();

        $response = $this->patch('/opnavi/admin/customers/'.$customer->id, [
            'sales_memo' => '別タブで編集中のメモ',
        ]);

        $response->assertRedirect('/opnavi/admin/customers');
        $response->assertSessionHas('error', '対象の顧客が見つかりません。別タブで削除された可能性があります。一覧を再読み込みしてください。');
    }

    public function test_deleted_customer_drawer_request_returns_not_found_message(): void
    {
        $customer = Customer::create($this->customerData());
        $customer->delete();

        $response = $this->get('/opnavi/admin/customers/'.$customer->id.'?modal=1', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertNotFound();
        $response->assertSee('対象の顧客が見つかりません。別タブで削除された可能性があります。一覧を再読み込みしてください。');
    }

    public function test_user_customer_list_hides_admin_only_actions(): void
    {
        Customer::create($this->customerData());

        $response = $this->get('/opnavi/user/customers');

        $response->assertOk();
        $response->assertSee('顧客一覧');
        $response->assertDontSee('CSVインポート');
        $response->assertDontSee('一括担当者設定');
        $response->assertDontSee('営業ダッシュボード');
        $response->assertSee(route('user.customers.show', ['customer' => 1]), false);
    }

    public function test_user_customer_detail_hides_basic_edit_and_delete_actions(): void
    {
        $customer = Customer::create($this->customerData());

        $response = $this->get('/opnavi/user/customers/'.$customer->id);

        $response->assertOk();
        $response->assertSee('基本情報');
        $response->assertSee('履歴を登録');
        $response->assertSee('保存');
        $response->assertSee('action="'.route('user.customers.update', $customer).'"', false);
        $response->assertDontSee('顧客を削除');
        $response->assertDontSee('>削除<', false);
        $response->assertDontSee('action="'.route('customers.update', $customer).'"', false);
    }

    public function test_user_customer_detail_updates_only_allowed_fields(): void
    {
        $owner = User::factory()->create(['name' => '砂澤', 'is_active' => true]);
        $customer = Customer::create($this->customerData([
            'business_name' => '変更前事業者',
            'owner_id' => $owner->id,
            'contact_phone' => '09011112222',
            'next_action_at' => null,
            'sales_memo' => '変更前メモ',
        ]));

        $response = $this->patch('/opnavi/user/customers/'.$customer->id, [
            'business_name' => '変更されてはいけない',
            'owner_id' => null,
            'contact_phone' => '09099998888',
            'next_action_at' => '2026-07-31',
            'sales_memo' => 'ユーザー画面で更新',
        ]);

        $response->assertRedirect('/opnavi/user/customers/'.$customer->id);
        $response->assertSessionHas('status', '保存しました');

        $customer->refresh();

        $this->assertSame('変更前事業者', $customer->business_name);
        $this->assertEquals($owner->id, $customer->owner_id);
        $this->assertSame('09099998888', $customer->contact_phone);
        $this->assertSame('2026-07-31', $customer->next_action_at->format('Y-m-d'));
        $this->assertSame('ユーザー画面で更新', $customer->sales_memo);
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
