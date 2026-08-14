<x-layouts.app title="オペナビ一覧">
    @php
        $isUserScreen = request()->routeIs('user.*');
        $indexRoute = $isUserScreen ? 'user.customers.index' : 'customers.index';
        $showRoute = $isUserScreen ? 'user.customers.show' : 'customers.show';
        $updateRoute = $isUserScreen ? 'user.customers.update' : 'customers.update';
        $currentSortBy = $filters['sort_by'] ?? 'next_action_at';
        $currentSortOrder = $filters['sort_order'] ?? 'asc';
        $activeHeaderSortBy = request()->filled('sort_by') ? $currentSortBy : null;
        $nextSortOrder = fn (string $sortBy) => $activeHeaderSortBy === $sortBy && $currentSortOrder === 'asc' ? 'desc' : 'asc';
        $nextSortUrlParams = fn (string $sortBy) => $activeHeaderSortBy === $sortBy && $currentSortOrder === 'desc'
            ? request()->except('page', 'sort_by', 'sort_order')
            : array_merge(request()->except('page', 'sort_by', 'sort_order'), ['sort_by' => $sortBy, 'sort_order' => $nextSortOrder($sortBy)]);
        $sortIndicator = fn (string $sortBy) => $activeHeaderSortBy === $sortBy ? ($currentSortOrder === 'asc' ? ' ↑' : ' ↓') : '';
        $sortUrl = fn (string $sortBy) => route($indexRoute, $nextSortUrlParams($sortBy));
    @endphp

    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ</p>
            <h1>顧客一覧</h1>
        </div>
        @unless ($isUserScreen)
            <button class="button primary" type="button" data-import-open>CSVインポート</button>
        @endunless
    </div>

    @if (session('import_warnings'))
        <div class="toast warning">
            <strong>インポート時にスキップした行があります。</strong>
            @foreach (session('import_warnings') as $warning)
                <div>{{ $warning }}</div>
            @endforeach
        </div>
    @endif

    <div class="list-layout">
        <form class="filters" method="get" action="{{ route($indexRoute) }}">
            <a class="button today-action-button" href="{{ route($indexRoute, ['today_action' => 1]) }}">当日対応</a>
            <label class="filters__keyword">
                事業者名・住所・営業メモ
                <input type="search" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="検索">
            </label>
            <label>
                ステータス
                <select name="status">
                    <option value="">すべて</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button primary" type="submit">検索</button>
                <a class="button" href="{{ route($indexRoute) }}">条件をクリア</a>
            </div>
        </form>

        @if (session('status'))
            <div class="toast success">{{ session('status') }}</div>
        @endif

        <section class="table-panel" data-customer-list-panel>
            @if ($customers->count() === 0)
                <div class="empty-state">
                    @if (request()->query())
                        条件に一致する顧客が見つかりませんでした。
                    @else
                        @if ($isUserScreen)
                            まだ表示できる顧客がありません。
                        @else
                            まだ顧客が登録されていません。CSVインポートから顧客リストを登録してください。
                        @endif
                    @endif
                </div>
            @else
                @unless ($isUserScreen)
                    <form id="bulk-owner-form" class="bulk-actions" method="post" action="{{ route('customers.bulk-owner') }}" data-bulk-owner-form>
                        @csrf
                        @method('patch')
                        <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                        <label>
                            一括担当者設定
                            <select name="owner_id">
                                <option value="">未担当</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="button" type="submit">選択した顧客に適用</button>
                        <span class="muted-text" data-bulk-selected-count>0件選択中</span>
                    </form>
                @endunless
                <div class="table-scroll">
                    <table class="customer-table {{ $isUserScreen ? 'customer-table--user' : '' }}">
                        <thead>
                            <tr>
                                @unless ($isUserScreen)
                                    <th class="select-col">
                                        <input type="checkbox" data-bulk-check-all aria-label="表示中の顧客をすべて選択">
                                    </th>
                                @endunless
                                <th class="sticky-col">事業者名</th>
                                <th class="registered-col">登録日</th>
                                <th class="region-col">都道府県</th>
                                <th class="area-col">店舗</th>
                                <th @class(['sortable-header', 'is-sorted' => $activeHeaderSortBy === 'ota_count'])>
                                    <a class="sortable-header__link" href="{{ $sortUrl('ota_count') }}" data-customer-sort-link aria-label="掲載OTA数を{{ $nextSortOrder('ota_count') === 'asc' ? '昇順' : '降順' }}で並び替え">
                                        <span>掲載OTA数</span>
                                        <span class="sortable-header__arrows" aria-hidden="true">{{ $activeHeaderSortBy === 'ota_count' ? trim($sortIndicator('ota_count')) : '↑↓' }}</span>
                                    </a>
                                </th>
                                <th>リクエスト予約</th>
                                <th class="status-col">ステータス</th>
                                <th class="owner-col">担当者</th>
                                <th @class(['sortable-header', 'is-sorted' => $activeHeaderSortBy === 'last_action_at'])>
                                    <a class="sortable-header__link" href="{{ $sortUrl('last_action_at') }}" data-customer-sort-link aria-label="最終アクションを{{ $nextSortOrder('last_action_at') === 'asc' ? '昇順' : '降順' }}で並び替え">
                                        <span>最終アクション</span>
                                        <span class="sortable-header__arrows" aria-hidden="true">{{ $activeHeaderSortBy === 'last_action_at' ? trim($sortIndicator('last_action_at')) : '↑↓' }}</span>
                                    </a>
                                </th>
                                <th @class(['sortable-header', 'is-sorted' => $activeHeaderSortBy === 'next_action_at'])>
                                    <a class="sortable-header__link" href="{{ $sortUrl('next_action_at') }}" data-customer-sort-link aria-label="次回アクションを{{ $nextSortOrder('next_action_at') === 'asc' ? '昇順' : '降順' }}で並び替え">
                                        <span>次回アクション</span>
                                        <span class="sortable-header__arrows" aria-hidden="true">{{ $activeHeaderSortBy === 'next_action_at' ? trim($sortIndicator('next_action_at')) : '↑↓' }}</span>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customers as $customer)
                                <tr data-customer-row="{{ $customer->id }}">
                                    @unless ($isUserScreen)
                                        <td class="select-col">
                                            <input type="checkbox" form="bulk-owner-form" name="customer_ids[]" value="{{ $customer->id }}" data-bulk-check aria-label="{{ $customer->business_name }}を選択">
                                        </td>
                                    @endunless
                                    <td class="sticky-col name-cell">
                                        <div class="name-cell__inner">
                                            <a href="{{ route($showRoute, array_merge(['customer' => $customer], request()->query())) }}" data-drawer-url="{{ route($showRoute, array_merge(['customer' => $customer, 'modal' => 1], request()->query())) }}" data-customer-id="{{ $customer->id }}">{{ $customer->business_name }}</a>
                                            <a class="detail-link" href="{{ route($showRoute, array_merge(['customer' => $customer], request()->query())) }}" target="_blank" rel="noreferrer" title="別タブで詳細を開く" aria-label="別タブで詳細を開く">
                                                <img src="{{ asset('images/external-link.png') }}" alt="">
                                            </a>
                                        </div>
                                    </td>
                                    <td class="registered-col">{{ optional($customer->registered_at)->format('Y/m/d') }}</td>
                                    <td class="region-col">{{ $customer->region }}</td>
                                    <td class="area-col">{{ $customer->area_name }}</td>
                                    <td>{{ $customer->ota_count }}</td>
                                    <td>{{ $customer->request_booking_status }}</td>
                                    <td class="status-col">
                                        <span class="status-pill status-pill--{{ [
                                            '未対応' => 'not-started',
                                            '連絡済み' => 'contacted',
                                            'やり取り中' => 'in-progress',
                                            'アポイント' => 'appointment',
                                            '商談中' => 'negotiation',
                                            '契約' => 'contracted',
                                            '失注' => 'lost',
                                        ][$customer->status] ?? 'default' }}" data-customer-status-pill>{{ $customer->status }}</span>
                                    </td>
                                    <td class="owner-col">
                                        @if ($isUserScreen)
                                            {{ $customer->owner?->name ?? '未担当' }}
                                        @else
                                            <form method="post" action="{{ route('customers.update', $customer) }}">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                                <select name="owner_id" onchange="this.form.submit()">
                                                    <option value="">未担当</option>
                                                    @foreach ($users as $user)
                                                        <option value="{{ $user->id }}" @selected($customer->owner_id === $user->id)>{{ $user->name }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @endif
                                    </td>
                                    <td>{{ optional($customer->last_action_at)->format('Y/m/d') ?? '-' }}</td>
                                    <td>
                                        <form method="post" action="{{ route($updateRoute, $customer) }}" class="date-inline">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                            <div class="list-date-picker" data-list-date-picker data-submit-on-apply="true">
                                                <input type="hidden" name="next_action_at" value="{{ optional($customer->next_action_at)->format('Y-m-d') }}" data-list-date-value>
                                                <button
                                                    class="list-date-picker__trigger"
                                                    type="button"
                                                    aria-haspopup="dialog"
                                                    aria-expanded="false"
                                                    aria-controls="next-action-calendar-{{ $customer->id }}"
                                                >
                                                    <span data-list-date-label></span>
                                                    <img class="list-date-picker__icon" src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true">
                                                </button>
                                                <section
                                                    id="next-action-calendar-{{ $customer->id }}"
                                                    class="list-date-picker__calendar"
                                                    role="dialog"
                                                    aria-label="次回アクション日を選択"
                                                    hidden
                                                >
                                                    <header class="list-date-picker__head">
                                                        <button class="list-date-picker__nav" type="button" data-prev-month aria-label="前月">‹</button>
                                                        <h2 class="list-date-picker__month" data-month-label></h2>
                                                        <button class="list-date-picker__nav" type="button" data-next-month aria-label="翌月">›</button>
                                                    </header>
                                                    <div class="list-date-picker__weekdays" aria-hidden="true">
                                                        <span>日</span>
                                                        <span>月</span>
                                                        <span>火</span>
                                                        <span>水</span>
                                                        <span>木</span>
                                                        <span>金</span>
                                                        <span>土</span>
                                                    </div>
                                                    <div class="list-date-picker__dates" data-dates role="grid" aria-label="日付"></div>
                                                    <footer class="list-date-picker__foot">
                                                        <button class="list-date-picker__text-button" type="button" data-clear>クリア</button>
                                                        <button class="list-date-picker__text-button" type="button" data-today>今日</button>
                                                    </footer>
                                                </section>
                                            </div>
                                            @if ($customer->next_action_at)
                                                @if ($customer->next_action_at->isToday())
                                                    <span class="badge danger">本日対応</span>
                                                @elseif ($customer->next_action_at->isPast() && ! $customer->next_action_at->isToday())
                                                    <span class="badge danger">期限切れ</span>
                                                @endif
                                            @else
                                                <span class="muted-text">未設定</span>
                                            @endif
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $customers->links() }}
            @endif
        </section>
    </div>

    <div class="drawer" data-drawer hidden>
        <div class="drawer__backdrop" data-drawer-close></div>
        <section class="drawer__panel">
            <button class="icon-button drawer__close" type="button" data-drawer-close>×</button>
            <div data-drawer-body>読み込み中...</div>
        </section>
    </div>

    @unless ($isUserScreen)
        <div class="modal" data-import-modal @if(!session('open_import')) hidden @endif>
            <div class="modal__backdrop" data-import-close></div>
            <section class="modal__panel">
                <button class="icon-button modal__close" type="button" data-import-close>×</button>
                <h2>CSVインポート</h2>
                @if (session('import_errors'))
                    <div class="toast error in-modal" data-import-errors>
                        @foreach (session('import_errors') as $importError)
                            <div>{{ $importError }}</div>
                        @endforeach
                    </div>
                @endif
                <form method="post" action="{{ route('customers.import') }}" enctype="multipart/form-data" class="stack-form" data-import-form>
                    @csrf
                    <a class="button" href="{{ asset('templates/opnavi_import_template.csv') }}" download>テンプレートをダウンロード</a>
                    <label>
                        CSVファイル
                        <input type="file" name="csv_file" accept=".csv,text/csv" required>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="confirm_duplicates" value="1">
                        重複した事業者名 + 住所は更新して取り込む
                    </label>
                    <button class="button primary" type="submit">インポート</button>
                </form>
            </section>
        </div>
    @endunless
</x-layouts.app>
