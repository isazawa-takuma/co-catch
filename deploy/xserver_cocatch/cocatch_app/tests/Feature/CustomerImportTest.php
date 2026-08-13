<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

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

        $this->assertDatabaseHas('opnavi_customers', [
            'business_name' => '取込対象事業者',
            'address' => '埼玉県さいたま市1-2-3',
            'store_count' => 3,
            'monthly_open_days' => 20,
            'owner_id' => 1,
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
