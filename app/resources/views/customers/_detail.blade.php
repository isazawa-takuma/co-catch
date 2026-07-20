@if (session('status') && request()->boolean('modal') && session('status_area') !== 'activity')
    <div class="toast success in-drawer">{{ session('status') }}</div>
@endif

@php
    $detailQuery = request()->except('modal');
    $drawerQuery = array_merge($detailQuery, ['modal' => 1]);
@endphp

<div class="detail-nav-bar">
    <div class="detail-nav">
        @if ($previousCustomer)
            <a
                class="button"
                href="{{ route('customers.show', array_merge(['customer' => $previousCustomer], $detailQuery)) }}"
                @if (request()->boolean('modal'))
                    data-drawer-url="{{ route('customers.show', array_merge(['customer' => $previousCustomer], $drawerQuery)) }}"
                    data-customer-id="{{ $previousCustomer->id }}"
                @endif
            >戻る</a>
        @else
            <span class="button disabled">戻る</span>
        @endif

        @if ($nextCustomer)
            <a
                class="button"
                href="{{ route('customers.show', array_merge(['customer' => $nextCustomer], $detailQuery)) }}"
                @if (request()->boolean('modal'))
                    data-drawer-url="{{ route('customers.show', array_merge(['customer' => $nextCustomer], $drawerQuery)) }}"
                    data-customer-id="{{ $nextCustomer->id }}"
                @endif
            >次へ</a>
        @else
            <span class="button disabled">次へ</span>
        @endif
    </div>
</div>

<div class="detail-grid">
    <section class="detail-section">
        <h2>基本情報</h2>
        <form method="post" action="{{ route('customers.update', $customer) }}" class="detail-form">
            @csrf
            @method('patch')
            @if (request()->boolean('modal'))
                <input type="hidden" name="modal" value="1">
            @endif
            <label>
                事業者名
                <input type="text" name="business_name" value="{{ $customer->business_name }}" required>
            </label>
            <label>
                都道府県
                <input type="text" name="region" value="{{ $customer->region }}" required>
            </label>
            <label>
                店舗
                <input type="text" name="area_name" value="{{ $customer->area_name }}" required>
            </label>
            <label class="span-2">
                住所
                <input type="text" name="address" value="{{ $customer->address }}" required>
            </label>
            <label class="span-2">
                Web URL
                <span class="input-with-action">
                    <input type="url" name="website_url" value="{{ $customer->website_url }}">
                    @if ($customer->website_url)
                        <a class="button" href="{{ $customer->website_url }}" target="_blank" rel="noreferrer">Webサイトを開く</a>
                    @endif
                </span>
            </label>
            <div class="copy-field">
                <span>電話番号（本社）</span>
                <strong>{{ $customer->head_office_phone ?: '-' }}</strong>
                @if ($customer->head_office_phone)
                    <button class="button small" type="button" data-copy="{{ $customer->head_office_phone }}">コピー</button>
                @endif
            </div>
            <div class="copy-field">
                <span>電話番号（OTA公開）</span>
                <strong>{{ $customer->public_phone ?: '-' }}</strong>
                @if ($customer->public_phone)
                    <button class="button small" type="button" data-copy="{{ $customer->public_phone }}">コピー</button>
                @endif
            </div>
            <label>
                担当者電話番号
                <input type="text" name="contact_phone" value="{{ $customer->contact_phone }}">
            </label>
            <div class="readonly-field">
                ステータス
                <strong class="status-pill status-pill--{{ [
                    '未対応' => 'not-started',
                    '連絡済み' => 'contacted',
                    'やり取り中' => 'in-progress',
                    'アポイント' => 'appointment',
                    '商談中' => 'negotiation',
                    '契約' => 'contracted',
                    '失注' => 'lost',
                ][$customer->status] ?? 'default' }}">{{ $customer->status }}</strong>
                <small>最新の架電・対応履歴から反映</small>
            </div>
            <label>
                担当者
                <select name="owner_id">
                    <option value="">未担当</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($customer->owner_id === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                次回アクション日
                <input type="date" name="next_action_at" value="{{ optional($customer->next_action_at)->format('Y-m-d') }}">
            </label>
            <label>
                登録日
                <input type="date" name="registered_at" value="{{ optional($customer->registered_at)->format('Y-m-d') }}" required>
            </label>
            <label class="span-2">
                営業メモ
                <textarea name="sales_memo" rows="4">{{ $customer->sales_memo }}</textarea>
            </label>
            <div class="span-2">
                <button class="button primary" type="submit">保存</button>
            </div>
        </form>
    </section>

    <section class="detail-section">
        <h2>体験・OTA</h2>
        <dl class="definition-list">
            <dt>体験内容</dt>
            <dd>{{ $customer->experience_title }}</dd>
            <dt>店舗数</dt>
            <dd>{{ $customer->store_count ?? '-' }}</dd>
            <dt>営業日数(1ヶ月)</dt>
            <dd>{{ $customer->monthly_open_days ?? '-' }}</dd>
            <dt>リクエスト予約</dt>
            <dd>{{ $customer->request_booking_status ?? '-' }}</dd>
            <dt>掲載OTA数</dt>
            <dd>{{ $customer->ota_count }}</dd>
        </dl>

        <div class="link-list">
            @forelse ($customer->otaLinks as $link)
                <a href="{{ $link->listing_url }}" target="_blank" rel="noreferrer">{{ $link->ota_name }}</a>
            @empty
                <span class="muted-text">OTAリンクは未登録です</span>
            @endforelse
        </div>
    </section>
</div>

<section class="detail-section activity-section">
    @if (session('status') && session('status_area') === 'activity')
        <div class="toast success in-drawer">{{ session('status') }}</div>
    @endif
    <h2>架電・対応履歴</h2>
    <form method="post" action="{{ route('customers.activities.store', $customer) }}" class="activity-form">
        @csrf
        @if (request()->boolean('modal'))
            <input type="hidden" name="modal" value="1">
        @endif
        <label>
            日時
            <input type="datetime-local" name="action_at" value="{{ now()->format('Y-m-d\TH:i') }}" data-current-datetime required>
        </label>
        <label>
            名前
            <select name="user_id" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($customer->owner_id === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            担当者
            <input type="text" name="contact_person">
        </label>
        <label>
            担当ステータス
            <select name="contact_status">
                <option value="">未選択</option>
                @foreach ($contactStatuses as $contactStatus)
                    <option value="{{ $contactStatus }}">{{ $contactStatus }}</option>
                @endforeach
            </select>
        </label>
        <label>
            ステータス
            <select name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($customer->status === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>
        <label class="wide">
            メモ
            <textarea name="memo" rows="3" required></textarea>
        </label>
        <button class="button primary" type="submit">履歴を登録</button>
    </form>

    <div class="activity-list">
        @forelse ($customer->activities->sortByDesc('action_at') as $activity)
            <article class="activity-item">
                <div class="activity-item__summary">
                    <div class="activity-summary-field">
                        <span>日時</span>
                        <strong>{{ $activity->action_at->format('Y/m/d H:i') }}</strong>
                    </div>
                    <div class="activity-summary-field">
                        <span>名前</span>
                        <strong>{{ $activity->user->name }}</strong>
                    </div>
                    <div class="activity-summary-field">
                        <span>担当者</span>
                        <strong>{{ $activity->contact_person ?: '-' }}</strong>
                    </div>
                    <div class="activity-summary-field">
                        <span>担当ステータス</span>
                        <strong>{{ $activity->contact_status ?: '-' }}</strong>
                    </div>
                    <div class="activity-summary-field">
                        <span>ステータス</span>
                        <strong class="status-pill status-pill--{{ [
                            '未対応' => 'not-started',
                            '連絡済み' => 'contacted',
                            'やり取り中' => 'in-progress',
                            'アポイント' => 'appointment',
                            '商談中' => 'negotiation',
                            '契約' => 'contracted',
                            '失注' => 'lost',
                        ][$activity->status] ?? 'default' }}">{{ $activity->status }}</strong>
                    </div>
                </div>
                <form method="post" action="{{ route('customers.activities.update', [$customer, $activity]) }}" class="activity-edit">
                    @csrf
                    @method('patch')
                    @if (request()->boolean('modal'))
                        <input type="hidden" name="modal" value="1">
                    @endif
                    <input type="hidden" name="action_at" value="{{ $activity->action_at->format('Y-m-d\TH:i') }}">
                    <input type="hidden" name="user_id" value="{{ $activity->user_id }}">
                    <input type="hidden" name="contact_person" value="{{ $activity->contact_person }}">
                    <input type="hidden" name="contact_status" value="{{ $activity->contact_status }}">
                    <input type="hidden" name="status" value="{{ $activity->status }}">
                    <label class="wide">
                        メモ
                        <textarea name="memo" rows="3" required>{{ $activity->memo }}</textarea>
                    </label>
                    <button class="button" type="submit">メモを更新</button>
                </form>
            </article>
        @empty
            <p class="empty-state">まだ架電・対応履歴はありません。</p>
        @endforelse
    </div>
</section>
