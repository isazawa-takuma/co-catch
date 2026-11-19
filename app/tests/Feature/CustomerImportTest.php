<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerImportTest extends TestCase
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

    public function test_csv_import_creates_customers_and_skips_short_open_days(): void
    {
        $csv = implode("\n", [
            $this->csvLine(['登録日', '事業者名', '地域', '店舗', '住所', '電話番号（OTA公開）', '体験内容', '国内掲載OTA', '営業日数(1ヶ月)', 'リクエスト予約', 'OTA_URL（メイン4社）', '店舗数', 'ステータス', '担当者']),
            $this->csvLine(['2026/07/20', '取込対象事業者', '埼玉県', 'さいたま店', '埼玉県さいたま市1-2-3', '048-000-0000', '陶芸体験', 'じゃらん', '20', 'あり', 'じゃらん: https://example.com/jalan', '3店舗', '未対応', '砂澤']),
            $this->csvLine(['2026/07/20', '短期営業事業者', '埼玉県', '川口店', '埼玉県川口市1-2-3', '048-111-1111', 'ガラス体験', 'じゃらん', '8', 'あり', 'じゃらん: https://example.com/skip', '1', '未対応', '荒']),
            $this->csvLine(['', '', '', '', '', '', '', '', '', '', '', '', '', '']),
        ]);

        $response = $this->post('/opnavi/admin/customers/import', [
            'csv_file' => $this->uploadedCsv($csv),
        ]);

        $response->assertRedirect('/opnavi/admin/customers');
        $response->assertSessionHas('status', 'sample.csvのインポートに成功しました（営業日数12日未満のため1件をスキップしました）');

        $owner = User::where('name', '砂澤')->firstOrFail();

        $this->assertDatabaseHas('opnavi_customers', [
            'business_name' => '取込対象事業者',
            'address' => '埼玉県さいたま市1-2-3',
            'store_count' => 3,
            'monthly_open_days' => 20,
            'owner_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('opnavi_ota_links', [
            'ota_name' => 'じゃらん',
            'listing_url' => 'https://example.com/jalan',
        ]);
        $this->assertDatabaseMissing('opnavi_customers', [
            'business_name' => '短期営業事業者',
        ]);
        $this->assertSame(1, Customer::count());
    }

    public function test_confirmed_duplicate_import_keeps_existing_customer_status(): void
    {
        $customer = Customer::create([
            'registered_at' => '2026-07-19',
            'business_name' => '重複事業者',
            'prefecture' => '埼玉県',
            'region' => '埼玉県',
            'area_name' => '旧店舗',
            'address' => '埼玉県さいたま市1-2-3',
            'experience_title' => '旧体験',
            'domestic_otas' => 'じゃらん',
            'ota_count' => 1,
            'store_count' => 1,
            'monthly_open_days' => 20,
            'request_booking_status' => 'あり',
            'status' => '商談中',
        ]);
        $csv = implode("\n", [
            $this->csvLine(['登録日', '事業者名', '地域', '店舗', '住所', '電話番号（OTA公開）', '体験内容', '国内掲載OTA', '営業日数(1ヶ月)', 'リクエスト予約', 'OTA_URL（メイン4社）', '店舗数', 'ステータス', '担当者']),
            $this->csvLine(['2026/07/20', '重複事業者', '埼玉県', '新店舗', '埼玉県さいたま市1-2-3', '048-000-0000', '新体験', 'じゃらん', '20', 'あり', 'じゃらん: https://example.com/jalan', '4店舗', '未対応', '砂澤']),
        ]);

        $response = $this->post('/opnavi/admin/customers/import', [
            'csv_file' => $this->uploadedCsv($csv),
            'confirm_duplicates' => '1',
        ]);

        $response->assertRedirect('/opnavi/admin/customers');
        $response->assertSessionHas('status', 'sample.csvのインポートに成功しました');

        $customer->refresh();
        $this->assertSame('商談中', $customer->status);
        $this->assertSame('新店舗', $customer->area_name);
        $this->assertSame('新体験', $customer->experience_title);
        $this->assertEquals(4, $customer->store_count);
    }

    public function test_import_counts_listed_providers_independently_of_their_urls(): void
    {
        $csv = $this->listingCsv([
            ['国内掲載OTA' => "じゃらん, Activity Japan, その他1, その他2,\n　", 'その他OTA名' => "一休.com\nOZmall",
                'OTA_URL（メイン4社）' => "https://www.jalan.net/plan/1\n\nActivity Japan=https://en.activityjapan.com/plan/2; アソビュー：https://www.asoview.com/base/3/?a=1&utm_source=chatgpt.com&a=2#plan （検索結果）"],
            ['事業者名' => '別事業者', '国内掲載OTA' => 'じゃらん, アソビュー！',
                'OTA_URL（メイン4社）' => "じゃらん: https://www.jalan.net/plan/4; アソビュー: https://www.asoview.com/base/3/\nhttps://www.asoview.com/base/3/\nhttps://www.asoview.com/item/5/"],
        ]);
        $this->post('/opnavi/admin/customers/import', ['csv_file' => $csv])->assertSessionHasNoErrors()->assertSessionHas('status');
        $customer = Customer::where('business_name', '照合先')->firstOrFail();
        $this->assertEquals(4, $customer->ota_count);
        $this->assertNull($customer->website_url);
        $this->assertCount(5, $customer->otaLinks);
        $this->assertDatabaseHas('opnavi_ota_links', ['customer_id' => $customer->id, 'ota_name' => 'Activity Japan', 'listing_url' => 'https://en.activityjapan.com/plan/2']);
        $this->get(route('customers.show', $customer))->assertSee('一休.com（URL未登録）')->assertSee('OZmall（URL未登録）')
            ->assertSee('参考リンク（掲載OTA数の対象外）')->assertSee('https://www.asoview.com/base/3/?a=1&a=2#plan')->assertDontSee('chatgpt.com')
            ->assertDontSee('href=""', false);
        $other = Customer::where('business_name', '別事業者')->firstOrFail();
        $this->assertEquals(2, $other->ota_count);
        $this->assertCount(3, $other->otaLinks);
    }

    public function test_listing_repair_previews_then_only_replaces_target_fields_and_is_repeatable(): void
    {
        $file = $this->listingCsv([['国内掲載OTA' => 'じゃらん, Activity Japan']]);
        $this->post('/opnavi/admin/customers/import', ['csv_file' => $file])->assertSessionHas('status');
        $customer = Customer::where('business_name', '照合先')->firstOrFail();
        $customer->update(['ota_count' => 3, 'sales_memo' => '大切な営業記録', 'website_url' => '未確認', 'status' => '商談中', 'owner_id' => auth()->id()]);
        $customer->otaLinks()->delete();
        $args = ['csv' => $file->getRealPath()];
        $this->artisan('opnavi:repair-listings', $args)->expectsOutput('差分あり: 1')->assertSuccessful();
        $this->assertEquals(3, $customer->fresh()->ota_count);
        $this->assertSame(0, $customer->otaLinks()->count());
        $this->assertSame('未確認', $customer->fresh()->website_url);

        $this->artisan('opnavi:repair-listings', $args + ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('opnavi_customers', ['id' => $customer->id, 'ota_count' => 2, 'website_url' => null,
            'sales_memo' => '大切な営業記録', 'status' => '商談中', 'owner_id' => auth()->id()]);
        $this->assertSame(2, $customer->otaLinks()->count());
        $this->artisan('opnavi:repair-listings', $args + ['--apply' => true])->expectsOutput('差分あり: 0')->assertSuccessful();
        $customer->otaLinks()->create(['ota_name' => '手動登録', 'listing_url' => 'https://example.org/manual']);
        $this->artisan('opnavi:repair-listings', $args + ['--apply' => true])->expectsOutput('要確認: 1')->assertSuccessful();
        $this->assertSame(3, $customer->otaLinks()->count());
    }

    public function test_listing_repair_skips_ambiguous_and_unregistered_rows(): void
    {
        $file = $this->listingCsv([[]]);
        $this->post('/opnavi/admin/customers/import', ['csv_file' => $file])->assertSessionHas('status');
        $customer = Customer::where('business_name', '照合先')->firstOrFail();
        $customer->update(['ota_count' => 8]);
        $ambiguous = $this->listingCsv([[], [], ['事業者名' => '新規']]);
        $this->artisan('opnavi:repair-listings', ['csv' => $ambiguous->getRealPath(), '--apply' => true])
            ->expectsOutput('未登録: 1')->expectsOutput('要確認: 1')->assertSuccessful();
        $this->assertEquals(8, $customer->fresh()->ota_count);
        $this->assertSame(1, Customer::count());
    }

    private function listingCsv(array $records): UploadedFile
    {
        $defaults = ['登録日' => '2026/07/20', '事業者名' => '照合先', '地域' => '東京都', '店舗' => '東京店',
            '住所' => '東京都内1-2-3', '電話番号（OTA公開）' => '', '体験内容' => '体験', '国内掲載OTA' => 'じゃらん',
            '営業日数(1ヶ月)' => '20', 'リクエスト予約' => 'あり', 'OTA_URL（メイン4社）' => 'https://www.jalan.net/plan/1',
            'ステータス' => '', '担当者' => '', 'その他OTA名' => '', 'HP_URL' => '未確認'];
        $lines = [$this->csvLine(array_keys($defaults))];
        foreach ($records as $record) {
            $lines[] = $this->csvLine(array_replace($defaults, $record));
        }
        return $this->uploadedCsv(implode("\n", $lines));
    }

    private function uploadedCsv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'opnavi_csv_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'sample.csv', 'text/csv', null, true);
    }

    private function csvLine(array $values): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $values);
        rewind($handle);
        $line = rtrim(stream_get_contents($handle), "\n");
        fclose($handle);

        return $line;
    }
}
