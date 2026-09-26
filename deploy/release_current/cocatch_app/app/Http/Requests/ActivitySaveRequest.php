<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivitySaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedStatuses = array_values(array_unique(array_merge(Activity::STATUSES, Customer::STATUSES)));

        return [
            'action_at' => ['required', 'date'],
            'user_id' => ['required', 'exists:users,id'],
            'rank' => ['required', Rule::in(Activity::RANKS)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_status' => ['nullable', Rule::in(Activity::CONTACT_STATUSES)],
            'status' => ['required', Rule::in($allowedStatuses)],
            'sales_owner_id' => [
                Rule::requiredIf(fn () => $this->input('status') === 'APO'),
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'sales')->where('is_active', true)),
            ],
            'memo' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_owner_id.required' => 'APOの履歴を登録する場合は営業担当を選択してください。',
            'sales_owner_id.exists' => '有効な営業担当を選択してください。',
        ];
    }
}
