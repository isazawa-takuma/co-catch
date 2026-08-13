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
            'next_action_at' => ['nullable', 'date'],
            'sales_memo' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }
}
