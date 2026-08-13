<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerBulkOwnerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['integer', 'exists:opnavi_customers,id'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_ids.required' => '担当者を一括設定する顧客を選択してください。',
            'customer_ids.min' => '担当者を一括設定する顧客を選択してください。',
        ];
    }
}
