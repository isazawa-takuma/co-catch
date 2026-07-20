<?php

namespace App\Services\Opnavi;

use App\Models\Customer;

class CustomerService
{
    public function update(Customer $customer, array $data): Customer
    {
        if (array_key_exists('region', $data)) {
            $data['prefecture'] = $this->guessPrefecture($data['region']) ?? $data['region'];
        }

        $customer->update($data);

        return $customer;
    }

    public function bulkUpdateOwner(array $customerIds, ?int $ownerId): int
    {
        return Customer::whereIn('id', $customerIds)->update(['owner_id' => $ownerId]);
    }

    private function guessPrefecture(string $value): ?string
    {
        if (preg_match('/(北海道|東京都|京都府|大阪府|.{2,3}県)/u', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
