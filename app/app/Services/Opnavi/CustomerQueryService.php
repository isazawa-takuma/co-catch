<?php

namespace App\Services\Opnavi;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
            $this->applyKeywordFilter($query, $keyword);
        }

        foreach (['region', 'status', 'owner_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->boolean('today_action')) {
            $query->whereDate('next_action_at', now()->toDateString());
        } elseif ($request->input('chip') === 'today') {
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

    private function applyKeywordFilter(Builder $query, string $keyword): void
    {
        foreach ($this->keywordTerms($keyword) as $term) {
            $query->where(function (Builder $inner) use ($term) {
                if ($this->shouldUseFullText($term)) {
                    $inner->whereRaw(
                        'MATCH (business_name, address, sales_memo) AGAINST (? IN BOOLEAN MODE)',
                        [$this->booleanPhrase($term)]
                    );
                } else {
                    $this->applyTextLikeKeywordFilter($inner, $term);
                }

                $this->applyPhoneKeywordFilter($inner, $term);
            });
        }
    }

    private function keywordTerms(string $keyword): array
    {
        return preg_split('/\s+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function shouldUseFullText(string $term): bool
    {
        return DB::connection()->getDriverName() === 'mysql' && mb_strlen($term) > 1;
    }

    private function booleanPhrase(string $term): string
    {
        return '+"'.str_replace(['\\', '"'], ['\\\\', '\"'], $term).'"';
    }

    private function applyTextLikeKeywordFilter(Builder $query, string $term): void
    {
        $query->where('business_name', 'like', "%{$term}%")
            ->orWhere('address', 'like', "%{$term}%")
            ->orWhere('sales_memo', 'like', "%{$term}%");
    }

    private function applyPhoneKeywordFilter(Builder $query, string $term): void
    {
        $query->orWhere('head_office_phone', 'like', "%{$term}%")
            ->orWhere('public_phone', 'like', "%{$term}%")
            ->orWhere('contact_phone', 'like', "%{$term}%");
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
