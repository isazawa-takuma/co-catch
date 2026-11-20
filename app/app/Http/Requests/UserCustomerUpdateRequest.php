<?php

namespace App\Http\Requests;

class UserCustomerUpdateRequest extends CustomerDetailRequest
{
    public function rules(): array
    {
        return [
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'next_action_at' => $this->nextActionDateRules(),
            'next_action_alert_enabled' => ['nullable', 'boolean'],
            'sales_memo' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }
}
