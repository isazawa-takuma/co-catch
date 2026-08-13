<x-layouts.app title="オペナビ一覧">
    @php
        $isUserScreen = request()->routeIs('user.*');
        $indexRoute = $isUserScreen ? 'user.customers.index' : 'customers.index';
        $showRoute = $isUserScreen ? 'user.customers.show' : 'customers.show';
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
            <label>
                事業者名・都道府県・住所
                <input type="search" name="keyword" value="{{ $filters['keyword'] ?? '' }}" placeholder="検索">
            </label>
            <label>
                都道府県
                <select name="region">
                    <option value="">すべて</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region }}" @selected(($filters['region'] ?? '') === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                担当者
                <select name="owner_id">
                    <option value="">すべて</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string)($filters['owner_id'] ?? '') === (string)$user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
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
            <label>
                並び替え
                <select name="sort_by">
                    <option value="registered_at" @selected(($filters['sort_by'] ?? 'registered_at') === 'registered_at')>登録日</option>
                    <option value="last_action_at" @selected(($filters['sort_by'] ?? '') === 'last_action_at')>最終アクション日</option>
                    <option value="next_action_at" @selected(($filters['sort_by'] ?? '') === 'next_action_at')>次回アクション日</option>
                    <option value="ota_count" @selected(($filters['sort_by'] ?? '') === 'ota_count')>掲載OTA数</option>
                    <option value="status" @selected(($filters['sort_by'] ?? '') === 'status')>ステータス</option>
                </select>
            </label>
            <label>
                順序
                <select name="sort_order">
                    <option value="desc" @selected(($filters['sort_order'] ?? 'desc') === 'desc')>降順</option>
                    <option value="asc" @selected(($filters['sort_order'] ?? '') === 'asc')>昇順</option>
                </select>
            </label>
            <label>
                表示件数
                <select name="per_page">
                    @foreach ([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected((int)($filters['per_page'] ?? 25) === $size)>{{ $size }}件</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-footer">
                <div class="filter-actions">
                    <button class="button primary" type="submit">検索</button>
                    <a class="button" href="{{ route($indexRoute) }}">条件をクリア</a>
                </div>
                <div class="chips">
                    <a href="{{ route($indexRoute, array_merge(request()->except('page'), ['chip' => 'not_started'])) }}">未対応</a>
                    <a href="{{ route($indexRoute, array_merge(request()->except('page'), ['chip' => 'today'])) }}">本日対応</a>
                    <a href="{{ route($indexRoute, array_merge(request()->except('page'), ['chip' => 'overdue'])) }}">期限切れ</a>
                    <a href="{{ route($indexRoute, array_merge(request()->except('page'), ['chip' => 'unassigned'])) }}">未担当</a>
                    <a href="{{ route($indexRoute, array_merge(request()->except('page'), ['chip' => 'has_ota'])) }}">OTA掲載あり</a>
                </div>
            </div>
        </form>

        @if (session('status'))
            <div class="toast success">{{ session('status') }}</div>
        @endif

        <section class="table-panel">
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
                    <table class="customer-table">
                        <thead>
                            <tr>
                                @unless ($isUserScreen)
                                    <th class="select-col">
                                        <input type="checkbox" data-bulk-check-all aria-label="表示中の顧客をすべて選択">
                                    </th>
                                @endunless
                                <th class="sticky-col">事業者名</th>
                                <th>登録日</th>
                                <th>都道府県</th>
                                <th>店舗</th>
                                <th>掲載OTA数</th>
                                <th>リクエスト予約</th>
                                <th class="status-col">ステータス</th>
                                <th class="owner-col">担当者</th>
                                <th>最終アクション</th>
                                <th>次回アクション</th>
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
                                    <td>{{ optional($customer->registered_at)->format('Y/m/d') }}</td>
                                    <td>{{ $customer->region }}</td>
                                    <td>{{ $customer->area_name }}</td>
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
                                        ][$customer->status] ?? 'default' }}">{{ $customer->status }}</span>
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
                                        <form method="post" action="{{ route('customers.update', $customer) }}" class="date-inline">
                                            @csrf
                                            @method('patch')
                                            <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                                            <input type="date" name="next_action_at" value="{{ optional($customer->next_action_at)->format('Y-m-d') }}" onchange="this.form.submit()">
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
