<?php

namespace App\Services\Opnavi;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerActivityService
{
    public function create(Customer $customer, array $data): Activity
    {
        $salesOwnerId = $this->pullSalesOwnerId($data);
        $activity = $customer->activities()->create($data);
        $this->syncSalesOwner($customer, $activity->status, $salesOwnerId);
        $this->syncLatestActivity($customer);

        return $activity;
    }

    public function update(Customer $customer, Activity $activity, array $data): Activity
    {
        abort_unless((int) $activity->customer_id === (int) $customer->id, 404);

        $salesOwnerId = $this->pullSalesOwnerId($data);
        $activity->update($data);
        $this->syncSalesOwner($customer, $activity->status, $salesOwnerId);
        $this->syncLatestActivity($customer);

        return $activity;
    }

    public function delete(Customer $customer, Activity $activity): void
    {
        abort_unless((int) $activity->customer_id === (int) $customer->id, 404);

        $activity->delete();
        $this->syncLatestActivity($customer);
    }

    public function syncLatestActivity(Customer $customer): void
    {
        $latestActivity = $customer->activities()
            ->latest('action_at')
            ->latest('id')
            ->first();

        if (! $latestActivity) {
            $customer->update([
                'status' => '未対応',
                'last_action_at' => null,
                'last_action_summary' => null,
            ]);

            return;
        }

        $customer->update([
            'status' => $latestActivity->status,
            'last_action_at' => $latestActivity->action_at->toDateString(),
            'last_action_summary' => Str::limit($latestActivity->memo, 80),
        ]);
    }

    private function pullSalesOwnerId(array &$data): ?int
    {
        if (! array_key_exists('sales_owner_id', $data)) {
            return null;
        }

        $salesOwnerId = $data['sales_owner_id'];
        unset($data['sales_owner_id']);

        return $salesOwnerId ? (int) $salesOwnerId : null;
    }

    private function syncSalesOwner(Customer $customer, string $status, ?int $salesOwnerId): void
    {
        if ($status !== 'APO' || $salesOwnerId === null) {
            return;
        }

        $customer->forceFill(['sales_owner_id' => $salesOwnerId])->save();
    }
}
