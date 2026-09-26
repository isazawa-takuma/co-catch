<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registered_at' => ['sometimes', 'date'],
            'business_name' => ['sometimes', 'required', 'string', 'max:255'],
            'region' => ['sometimes', 'required', 'string', 'max:255'],
            'area_name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:1000'],
            'head_office_phone' => ['nullable', 'string', 'max:255'],
            'public_phone' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'experience_title' => ['sometimes', 'required', 'string', 'max:255'],
            'store_count' => ['nullable', 'integer', 'min:0'],
            'monthly_open_days' => ['nullable', 'integer', 'min:0'],
            'request_booking_status' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(Customer::STATUSES)],
            'owner_id' => ['nullable', 'exists:users,id'],
            'sales_owner_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'sales')->where('is_active', true)),
            ],
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
