<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\DailyCallResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_user_menu_links_to_mypage(): void
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'role' => 'sales',
        ]);

        $response = $this->actingAs($user)->get('/opnavi/user/customers');

        $response->assertOk();
        $response->assertSee('マイページ');
        $response->assertSee(route('mypage.show'), false);
    }

    public function test_mypage_shows_today_count_and_status_counts_without_activity_table(): void
    {
        Carbon::setTestNow('2026-08-25 15:00:00');

        $user = User::factory()->create([
            'role' => 'sales',
        ]);
        $otherUser = User::factory()->create([
            'role' => 'sales',
        ]);
        $customer = Customer::create($this->customerData([
            'business_name' => 'レンタル銘仙イロハトリ',
            'owner_id' => $user->id,
        ]));
        $otherCustomer = Customer::create($this->customerData([
            'business_name' => '別の事業者',
            'owner_id' => $otherUser->id,
        ]));

        Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'A',
            'action_at' => '2026-08-25 14:50:00',
            'status' => 'コール',
            'memo' => '今日登録したメモ',
        ]);
        Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'B',
            'action_at' => '2026-08-24 14:50:00',
            'status' => 'コール',
            'memo' => '今日登録した別メモ',
        ]);
        Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'B',
            'action_at' => '2026-08-25 14:55:00',
            'status' => 'NG',
            'memo' => '今日登録したNGメモ',
        ]);
        Activity::create([
            'customer_id' => $otherCustomer->id,
            'user_id' => $otherUser->id,
            'rank' => 'A',
            'action_at' => '2026-08-25 14:50:00',
            'status' => 'コール',
            'memo' => '他ユーザーのメモ',
        ]);

        Carbon::setTestNow('2026-08-24 15:00:00');
        Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'C',
            'action_at' => '2026-08-24 14:50:00',
            'status' => 'コール',
            'memo' => '昨日登録したメモ',
        ]);

        Carbon::setTestNow('2026-08-25 15:00:00');

        $response = $this->actingAs($user)->get('/opnavi/my_page');

        $response->assertOk();
        $response->assertDontSee('合計架電数');
        $response->assertSee('<h2 id="mypage-summary-title" class="mypage-summary__title">本日の成績</h2>', false);
        $response->assertSee('本日の架電数');
        $response->assertSee('<strong>3</strong>', false);
        $response->assertSeeInOrder([
            'コール',
            '<strong>2</strong>',
            'NG',
            '<strong>1</strong>',
        ], false);
        $response->assertSee('本日の成績を確定');
        $response->assertSee('class="mypage-summary"', false);
        $response->assertDontSee('<h2>本日の架電成績</h2>', false);
        $response->assertSee('data-mypage-refresh', false);
        $response->assertSee('>更新</button>', false);
        $response->assertSee('data-mypage-confirm-modal', false);
        $response->assertDontSee('登録日時');
        $response->assertDontSee('事業者名');
        $response->assertDontSee('今日登録したメモ');
        $response->assertDontSee('昨日登録したメモ');
        $response->assertDontSee('他ユーザーのメモ');
    }

    public function test_user_can_confirm_today_call_result_only_once(): void
    {
        Carbon::setTestNow('2026-08-25 15:00:00');

        $user = User::factory()->create([
            'role' => 'sales',
        ]);
        $customer = Customer::create($this->customerData([
            'owner_id' => $user->id,
        ]));

        foreach (['コール', 'コール', 'NG'] as $index => $status) {
            Activity::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'rank' => 'A',
                'action_at' => "2026-08-25 14:5{$index}:00",
                'status' => $status,
                'memo' => "確定対象{$index}",
            ]);
        }

        $response = $this->actingAs($user)->post(route('mypage.confirm'));

        $response->assertRedirect(route('mypage.show'));
        $response->assertSessionHas('status', '本日の架電成績を確定しました。');

        $result = DailyCallResult::query()->firstOrFail();
        $this->assertSame($user->id, $result->user_id);
        $this->assertSame('2026-08-25', $result->result_date->toDateString());
        $this->assertSame(3, $result->total_count);
        $this->assertSame(['コール' => 2, 'NG' => 1], $result->status_counts);

        Activity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'rank' => 'A',
            'action_at' => '2026-08-25 15:05:00',
            'status' => 'APO',
            'memo' => '確定後の履歴',
        ]);

        $secondResponse = $this->post(route('mypage.confirm'));

        $secondResponse->assertRedirect(route('mypage.show'));
        $secondResponse->assertSessionHas('status', '本日の架電成績はすでに確定済みです。');
        $this->assertDatabaseCount('opnavi_daily_call_results', 1);
        $this->assertSame(3, $result->fresh()->total_count);

        $confirmedPage = $this->get(route('mypage.show'));
        $confirmedPage->assertOk();
        $confirmedPage->assertSee('本日の成績は確定済み');
        $confirmedPage->assertSee('data-mypage-confirm-open', false);
        $confirmedPage->assertSee('disabled', false);
        $confirmedPage->assertDontSee('data-mypage-confirm-modal', false);
        $confirmedPage->assertSee('<strong>3</strong>', false);

        Carbon::setTestNow('2026-08-26 09:00:00');
        $nextDayPage = $this->get(route('mypage.show'));
        $nextDayPage->assertOk();
        $nextDayPage->assertSee('本日の成績を確定');
        $nextDayPage->assertSee('data-mypage-confirm-modal', false);
    }

    public function test_mypage_filters_confirmed_daily_results_by_date_range(): void
    {
        Carbon::setTestNow('2026-08-25 15:00:00');

        $user = User::factory()->create([
            'role' => 'appointment',
        ]);
        $otherUser = User::factory()->create([
            'role' => 'appointment',
        ]);

        DailyCallResult::create([
            'user_id' => $user->id,
            'result_date' => '2026-08-20',
            'total_count' => 5,
            'status_counts' => ['NG' => 1, 'コール' => 3, 'メール' => 1],
            'confirmed_at' => '2026-08-20 18:00:00',
        ]);
        DailyCallResult::create([
            'user_id' => $user->id,
            'result_date' => '2026-08-10',
            'total_count' => 9,
            'status_counts' => ['APO' => 9],
            'confirmed_at' => '2026-08-10 18:00:00',
        ]);
        DailyCallResult::create([
            'user_id' => $otherUser->id,
            'result_date' => '2026-08-21',
            'total_count' => 7,
            'status_counts' => ['受付ブロック' => 7],
            'confirmed_at' => '2026-08-21 18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/opnavi/my_page?result_from=2026-08-15&result_to=2026-08-22');

        $response->assertOk();
        $response->assertSee('日付ごとの成績');
        $response->assertSee('name="result_from" value="2026-08-15"', false);
        $response->assertSee('name="result_to" value="2026-08-22"', false);
        $response->assertSee('2026/08/20');
        $response->assertSeeInOrder([
            'コール',
            '<strong>3</strong>',
            'NG',
            '<strong>1</strong>',
            'メール',
            '<strong>1</strong>',
        ], false);
        $response->assertDontSee('2026/08/10');
        $response->assertDontSee('受付ブロック');
    }

    public function test_mypage_rejects_an_end_date_before_the_start_date(): void
    {
        $user = User::factory()->create([
            'role' => 'appointment',
        ]);

        $response = $this->actingAs($user)->get('/opnavi/my_page?result_from=2026-08-20&result_to=2026-08-19');

        $response->assertSessionHasErrors([
            'result_to' => '終了日は開始日以降の日付を指定してください。',
        ]);
    }

    public function test_admin_sidebar_does_not_show_mypage_link(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get('/opnavi/admin/user_management');

        $response->assertOk();
        $response->assertDontSee('マイページ');
    }

    public function test_admin_cannot_access_mypage(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get('/opnavi/my_page');

        $response->assertRedirect('/login');
    }

    public function test_mypage_requires_login(): void
    {
        $response = $this->get('/opnavi/my_page');

        $response->assertRedirect('/login');
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
