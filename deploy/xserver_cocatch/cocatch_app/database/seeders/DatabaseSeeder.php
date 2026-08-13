<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\OtaLink;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $sunazawa = User::updateOrCreate(
            ['email' => 'sunazawa@example.local'],
            ['name' => '砂澤', 'password' => Hash::make('password'), 'role' => 'member', 'is_active' => true]
        );

        $ara = User::updateOrCreate(
            ['email' => 'ara@example.local'],
            ['name' => '荒', 'password' => Hash::make('password'), 'role' => 'member', 'is_active' => true]
        );

        if (Customer::count() === 0) {
            $customer = Customer::create([
                'registered_at' => now()->subDays(2)->toDateString(),
                'business_name' => 'サンプル体験株式会社',
                'prefecture' => '神奈川県',
                'region' => '神奈川県',
                'area_name' => '横浜店',
                'address' => '神奈川県横浜市中区サンプル1-2-3',
                'website_url' => 'https://example.com',
                'head_office_phone' => '03-0000-0000',
                'public_phone' => '045-000-0000',
                'experience_title' => '横浜まち歩き体験',
                'domestic_otas' => 'じゃらん, 楽天トラベル',
                'ota_count' => 2,
                'monthly_open_days' => 20,
                'request_booking_status' => 'あり',
                'status' => '未対応',
                'owner_id' => $sunazawa->id,
                'next_action_at' => now()->toDateString(),
            ]);

            OtaLink::create(['customer_id' => $customer->id, 'ota_name' => 'じゃらん', 'listing_url' => 'https://www.jalan.net/']);
            OtaLink::create(['customer_id' => $customer->id, 'ota_name' => '楽天トラベル', 'listing_url' => 'https://travel.rakuten.co.jp/']);
        }
    }
}
