<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserCustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'next_action_at' => ['nullable', 'date', 'after_or_equal:today'],
            'next_action_alert_enabled' => ['nullable', 'boolean'],
            'sales_memo' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'next_action_at.after_or_equal' => '次回アクション日は本日以降の日付を指定してください。',
        ];
    }
}
