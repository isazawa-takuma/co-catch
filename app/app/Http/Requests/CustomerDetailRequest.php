<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class CustomerDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function nextActionDateRules(): array
    {
        $saved = $this->route('customer')?->next_action_at;
        $value = $this->input('next_action_at');

        // The picker posts minutes even when the stored timestamp includes seconds.
        if ($saved && in_array($value, [
            $saved->format('Y-m-d\TH:i'), $saved->format('Y-m-d\TH:i:s'), $saved->toDateTimeString(),
        ], true)) {
            return ['exclude'];
        }

        return ['bail', 'nullable', 'date', 'after_or_equal:today'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attributeを入力してください。',
            'date' => ':attributeには正しい日付を入力してください。',
            'exists' => ':attributeの選択内容を確認してください。',
            'in' => ':attributeの選択内容を確認してください。',
            'boolean' => ':attributeの指定を確認してください。',
            'integer' => ':attributeは整数で入力してください。',
            'min' => ':attributeには:min以上の値を入力してください。',
            'string' => ':attributeは文字列で入力してください。',
            'max' => ':attributeは:max文字以内で入力してください。',
            'next_action_at.after_or_equal' => '次回アクション日は本日以降の日付を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'registered_at' => '登録日', 'business_name' => '事業者名', 'region' => '都道府県',
            'area_name' => '店舗', 'address' => '住所', 'website_url' => 'Web URL',
            'head_office_phone' => '電話番号（本社）', 'public_phone' => '電話番号（OTA公開）',
            'contact_phone' => '担当者電話番号', 'experience_title' => '体験内容', 'store_count' => '店舗数',
            'monthly_open_days' => '営業日数', 'request_booking_status' => 'リクエスト予約',
            'status' => 'ステータス', 'owner_id' => 'コール担当', 'sales_owner_id' => '営業担当',
            'next_action_at' => '次回アクション日', 'next_action_alert_enabled' => '重要アラート',
            'sales_memo' => '営業メモ', 'action_at' => '日時', 'user_id' => '履歴の登録者',
            'rank' => 'Rank', 'contact_person' => '相手担当者', 'contact_status' => '担当ステータス',
            'memo' => 'メモ', 'redirect_to' => '戻り先',
        ];
    }
}
