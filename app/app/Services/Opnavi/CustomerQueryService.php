<?php

namespace App\Services\Opnavi;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomerQueryService
{
    public function paginate(Request $request)
    {
        $query = Customer::query()->with('owner');

        $this->applyFilters($query, $request);

        $this->applySort($query, $request);

        return $query
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    public function activeUsers()
    {
        return User::where('is_active', true)->orderBy('id')->get();
    }

    public function regions()
    {
        return Customer::query()->select('region')->distinct()->orderBy('region')->pluck('region');
    }

    public function previousCustomer(Customer $customer, Request $request): ?Customer
    {
        return $this->adjacentCustomer($customer, $request, -1);
    }

    public function nextCustomer(Customer $customer, Request $request): ?Customer
    {
        return $this->adjacentCustomer($customer, $request, 1);
    }

    private function adjacentCustomer(Customer $customer, Request $request, int $offset): ?Customer
    {
        $query = Customer::query()->select('id');

        $this->applyFilters($query, $request);

        $this->applySort($query, $request);

        $ids = $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $currentIndex = $ids->search((int) $customer->id);
        if ($currentIndex === false) {
            return null;
        }

        $adjacentId = $ids->get($currentIndex + $offset);
        if (! $adjacentId) {
            return null;
        }

        return Customer::find($adjacentId);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($keyword = trim((string) $request->input('keyword'))) {
            $query->where(function ($inner) use ($keyword) {
                $inner->where('business_name', 'like', "%{$keyword}%")
                    ->orWhere('region', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhere('area_name', 'like', "%{$keyword}%")
                    ->orWhere('experience_title', 'like', "%{$keyword}%")
                    ->orWhere('sales_memo', 'like', "%{$keyword}%");
            });
        }

        foreach (['region', 'status', 'owner_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->input('chip') === 'today') {
            $query->whereDate('next_action_at', now()->toDateString());
        } elseif ($request->input('chip') === 'overdue') {
            $query->whereNotNull('next_action_at')->whereDate('next_action_at', '<', now()->toDateString());
        } elseif ($request->input('chip') === 'unassigned') {
            $query->whereNull('owner_id');
        } elseif ($request->input('chip') === 'has_ota') {
            $query->where('ota_count', '>', 0);
        } elseif ($request->input('chip') === 'not_started') {
            $query->where('status', '未対応');
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortBy = $this->sortBy($request);
        $sortOrder = $this->sortOrder($request);

        if ($sortBy === 'next_action_at') {
            $query->orderByRaw('next_action_at is null')
                ->orderBy('next_action_at', $sortOrder)
                ->orderByDesc('id');

            return;
        }

        $query->orderBy($sortBy, $sortOrder)
            ->orderByDesc('id');
    }

    public function sortBy(Request $request): string
    {
        $sortBy = $request->input('sort_by', 'next_action_at');
        $allowedSorts = ['last_action_at', 'next_action_at', 'ota_count', 'status'];

        return in_array($sortBy, $allowedSorts, true) ? $sortBy : 'next_action_at';
    }

    public function sortOrder(Request $request): string
    {
        $defaultOrder = $this->sortBy($request) === 'next_action_at' ? 'asc' : 'desc';

        return $request->input('sort_order', $defaultOrder) === 'desc' ? 'desc' : 'asc';
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);

        return in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
    }
}
