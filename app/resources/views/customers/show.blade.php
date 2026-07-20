<x-layouts.app title="{{ $customer->business_name }}">
    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ詳細</p>
            <h1>{{ $customer->business_name }}</h1>
        </div>
        <div class="header-actions">
            <a class="button" href="{{ route('customers.index', request()->query()) }}">一覧へ戻る</a>
            <form method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('この顧客を削除しますか？');">
                @csrf
                @method('delete')
                <button class="button danger" type="submit">削除</button>
            </form>
        </div>
    </div>

    @include('customers._detail', [
        'customer' => $customer,
        'users' => $users,
        'statuses' => $statuses,
        'contactStatuses' => $contactStatuses,
        'previousCustomer' => $previousCustomer,
        'nextCustomer' => $nextCustomer,
    ])
</x-layouts.app>
