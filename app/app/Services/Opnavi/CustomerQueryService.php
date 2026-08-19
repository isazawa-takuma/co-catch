<?php

namespace App\Services\Opnavi;

use App\Models\Customer;
use App\Models\User;
use App\Support\PhoneSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CustomerQueryService
{
    public function paginate(Request $request, ?int $ownerId = null)
    {
        $query = Customer::query()->with('owner');

        $this->applyOwnerScope($query, $ownerId);

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

    public function previousCustomer(Customer $customer, Request $request, ?int $ownerId = null): ?Customer
    {
        return $this->adjacentCustomer($customer, $request, -1, $ownerId);
    }

    public function nextCustomer(Customer $customer, Request $request, ?int $ownerId = null): ?Customer
    {
        return $this->adjacentCustomer($customer, $request, 1, $ownerId);
    }

    private function adjacentCustomer(Customer $customer, Request $request, int $offset, ?int $ownerId = null): ?Customer
    {
        $query = Customer::query()->select('opnavi_customers.id');

        $this->applyOwnerScope($query, $ownerId);

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

    private function applyOwnerScope(Builder $query, ?int $ownerId): void
    {
        if ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }
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

        if ($request->filled('next_action_from')) {
            $query->whereDate('next_action_at', '>=', $request->input('next_action_from'));
        }

        if ($request->filled('next_action_to')) {
            $query->whereDate('next_action_at', '<=', $request->input('next_action_to'));
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

    private function applyKeywordFilter(Builder $query, string $keyword): void
    {
        foreach ($this->keywordTerms($keyword) as $index => $term) {
            if ($this->shouldUseFullText($term)) {
                $this->ensureCustomerColumnsSelected($query);
                $alias = 'keyword_candidates_'.$index;
                $query->joinSub($this->mysqlKeywordCandidateSubquery($term), $alias, function (JoinClause $join) use ($alias) {
                    $join->on('opnavi_customers.id', '=', $alias.'.id');
                });

                continue;
            }

            $query->where(function (Builder $inner) use ($term) {
                $this->applyTextLikeKeywordFilter($inner, $term);
                $this->applyPhoneKeywordFilter($inner, $term);
            });
        }
    }

    private function ensureCustomerColumnsSelected(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('opnavi_customers.*');
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

    private function mysqlKeywordCandidateSubquery(string $term): QueryBuilder
    {
        $subQuery = DB::query()
            ->select('id')
            ->from('opnavi_customers')
            ->whereNull('deleted_at')
            ->whereRaw(
                'MATCH (business_name, address, sales_memo) AGAINST (? IN BOOLEAN MODE)',
                [$this->booleanPhrase($term)]
            );

        $normalizedTerm = PhoneSearch::normalize($term);
        if ($normalizedTerm === '') {
            return $subQuery;
        }

        $subQuery->union($this->mysqlPhoneNormalizedCandidateSubquery($normalizedTerm));

        if (strlen($normalizedTerm) >= PhoneSearch::MIN_TOKEN_LENGTH) {
            $subQuery->union(
                DB::query()
                    ->selectRaw('customer_id as id')
                    ->from('opnavi_customer_phone_indexes')
                    ->where('phone_token', $normalizedTerm)
            );
        }

        return $subQuery;
    }

    private function mysqlPhoneNormalizedCandidateSubquery(string $normalizedTerm): QueryBuilder
    {
        return DB::query()
            ->select('id')
            ->from('opnavi_customers')
            ->whereNull('deleted_at')
            ->where(function (QueryBuilder $phoneQuery) use ($normalizedTerm) {
                foreach (['head_office_phone_normalized', 'public_phone_normalized', 'contact_phone_normalized'] as $column) {
                    if (strlen($normalizedTerm) >= PhoneSearch::MIN_TOKEN_LENGTH) {
                        $phoneQuery->orWhere($column, $normalizedTerm)
                            ->orWhere($column, 'like', "{$normalizedTerm}%");
                    } else {
                        $phoneQuery->orWhere($column, 'like', "%{$normalizedTerm}%");
                    }
                }
            });
    }

    private function applyPhoneKeywordFilter(Builder $query, string $term): void
    {
        $normalizedTerm = PhoneSearch::normalize($term);

        if ($normalizedTerm === '') {
            return;
        }

        if (strlen($normalizedTerm) < PhoneSearch::MIN_TOKEN_LENGTH) {
            $query->orWhere(function (Builder $phoneQuery) use ($normalizedTerm) {
                foreach (['head_office_phone_normalized', 'public_phone_normalized', 'contact_phone_normalized'] as $column) {
                    $phoneQuery->orWhere($column, 'like', "%{$normalizedTerm}%");
                }
            });

            return;
        }

        $query->orWhere(function (Builder $phoneQuery) use ($normalizedTerm) {
            foreach (['head_office_phone_normalized', 'public_phone_normalized', 'contact_phone_normalized'] as $column) {
                $phoneQuery->orWhere($column, $normalizedTerm)
                    ->orWhere($column, 'like', "{$normalizedTerm}%");
            }

            $phoneQuery->orWhereExists(function ($subQuery) use ($normalizedTerm) {
                $subQuery->selectRaw('1')
                    ->from('opnavi_customer_phone_indexes')
                    ->whereColumn('opnavi_customer_phone_indexes.customer_id', 'opnavi_customers.id')
                    ->where('opnavi_customer_phone_indexes.phone_token', $normalizedTerm);
            });
        });
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortBy = $this->sortBy($request);
        $sortOrder = $this->sortOrder($request);

        if ($sortBy === 'next_action_at') {
            $query->orderByRaw('opnavi_customers.next_action_at is null')
                ->orderBy('opnavi_customers.next_action_at', $sortOrder)
                ->orderByDesc('opnavi_customers.id');

            return;
        }

        $query->orderBy('opnavi_customers.'.$sortBy, $sortOrder)
            ->orderByDesc('opnavi_customers.id');
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
