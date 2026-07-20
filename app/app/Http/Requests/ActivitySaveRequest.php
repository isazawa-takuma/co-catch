<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Activity;
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
        return [
            'action_at' => ['required', 'date'],
            'user_id' => ['required', 'exists:users,id'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_status' => ['nullable', Rule::in(Activity::CONTACT_STATUSES)],
            'status' => ['required', Rule::in(Customer::STATUSES)],
            'memo' => ['required', 'string'],
        ];
    }
}
