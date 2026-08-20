@if (session('status') && request()->boolean('modal') && session('status_area') !== 'activity')
    <div class="toast success in-drawer">{{ session('status') }}</div>
@endif

@php
    $isUserScreen = request()->routeIs('user.*');
    $showRoute = $isUserScreen ? 'user.customers.show' : 'customers.show';
    $customerUpdateRoute = $isUserScreen ? 'user.customers.update' : 'customers.update';
    $activityStoreRoute = $isUserScreen ? 'user.customers.activities.store' : 'customers.activities.store';
    $activityUpdateRoute = $isUserScreen ? 'user.customers.activities.update' : 'customers.activities.update';
    $detailQuery = request()->except('modal');
    $drawerQuery = array_merge($detailQuery, ['modal' => 1]);
@endphp

<div class="detail-nav-bar">
    <div class="detail-nav">
        @if ($previousCustomer)
            <a
                class="button"
                href="{{ route($showRoute, array_merge(['customer' => $previousCustomer], $detailQuery)) }}"
                @if (request()->boolean('modal'))
                    data-drawer-url="{{ route($showRoute, array_merge(['customer' => $previousCustomer], $drawerQuery)) }}"
                    data-customer-id="{{ $previousCustomer->id }}"
                @endif
            >戻る</a>
        @else
            <span class="button disabled">戻る</span>
        @endif

        @if ($nextCustomer)
            <a
                class="button"
                href="{{ route($showRoute, array_merge(['customer' => $nextCustomer], $detailQuery)) }}"
                @if (request()->boolean('modal'))
                    data-drawer-url="{{ route($showRoute, array_merge(['customer' => $nextCustomer], $drawerQuery)) }}"
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
        <form method="post" action="{{ route($customerUpdateRoute, $customer) }}" class="detail-form">
            @csrf
            @method('patch')
            @if (request()->boolean('modal'))
                <input type="hidden" name="modal" value="1">
            @endif
            <label>
                事業者名
                <input type="text" name="business_name" value="{{ $customer->business_name }}" required @readonly($isUserScreen)>
            </label>
            <label>
                都道府県
                <input type="text" name="region" value="{{ $customer->region }}" required @readonly($isUserScreen)>
            </label>
            <label>
                店舗
                <input type="text" name="area_name" value="{{ $customer->area_name }}" required @readonly($isUserScreen)>
            </label>
            <label class="span-2">
                住所
                <input type="text" name="address" value="{{ $customer->address }}" required @readonly($isUserScreen)>
            </label>
            <label class="span-2">
                Web URL
                <span class="input-with-action">
                    <input type="url" name="website_url" value="{{ $customer->website_url }}" @readonly($isUserScreen)>
                    @if ($customer->website_url)
                        <a class="button" href="{{ $customer->website_url }}" target="_blank" rel="noreferrer">Webサイトを開く</a>
                    @endif
                </span>
            </label>
            <div class="copy-field-group">
                <span class="detail-field-label">電話番号（本社）</span>
                <div class="copy-field">
                    <strong>{{ $customer->head_office_phone ?: '-' }}</strong>
                    @if ($customer->head_office_phone)
                        <button class="button small" type="button" data-copy="{{ $customer->head_office_phone }}">コピー</button>
                    @endif
                </div>
            </div>
            <div class="copy-field-group">
                <span class="detail-field-label">電話番号（OTA公開）</span>
                <div class="copy-field">
                    <strong>{{ $customer->public_phone ?: '-' }}</strong>
                    @if ($customer->public_phone)
                        <button class="button small" type="button" data-copy="{{ $customer->public_phone }}">コピー</button>
                    @endif
                </div>
            </div>
            <label>
                担当者電話番号
                <input type="text" name="contact_phone" value="{{ $customer->contact_phone }}">
            </label>
            <div class="readonly-field-group">
                <span class="detail-field-label">ステータス</span>
                <div class="readonly-field">
                    <strong class="status-pill status-pill--{{ \App\Models\Customer::statusClass($customer->status) }}" data-current-customer-status data-customer-id="{{ $customer->id }}">{{ $customer->status }}</strong>
                    <small>最新の架電・対応履歴から反映</small>
                </div>
            </div>
            <label>
                担当者
                <select name="owner_id" @disabled($isUserScreen)>
                    <option value="">未担当</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($customer->owner_id === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="span-2 next-action-alert-row">
                <label class="next-action-field">
                    次回アクション日
                    <div class="date-time-picker" data-date-time-picker>
                        <input type="hidden" name="next_action_at" value="{{ optional($customer->next_action_at)->format('Y-m-d\TH:i') }}" data-date-time-value>
                        <button
                            class="date-time-picker__trigger"
                            type="button"
                            aria-haspopup="dialog"
                            aria-expanded="false"
                            aria-controls="detail-next-action-datetime-{{ $customer->id }}"
                        >
                            <span data-date-time-label></span>
                            <img class="date-time-picker__icon" src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true">
                        </button>
                        <section
                            id="detail-next-action-datetime-{{ $customer->id }}"
                            class="date-time-picker__panel"
                            role="dialog"
                            aria-label="次回アクション日時を選択"
                            hidden
                        >
                            <div class="date-time-picker__grid">
                                <div>
                                    <header class="date-time-picker__head">
                                        <button class="date-time-picker__nav" type="button" data-prev-month aria-label="前月">‹</button>
                                        <h2 class="date-time-picker__month" data-month-label></h2>
                                        <button class="date-time-picker__nav" type="button" data-next-month aria-label="翌月">›</button>
                                    </header>
                                    <div class="date-time-picker__weekdays" aria-hidden="true">
                                        <span>日</span>
                                        <span>月</span>
                                        <span>火</span>
                                        <span>水</span>
                                        <span>木</span>
                                        <span>金</span>
                                        <span>土</span>
                                    </div>
                                    <div class="date-time-picker__dates" data-dates role="grid" aria-label="日付"></div>
                                </div>
                                <aside class="date-time-picker__time">
                                    <strong>時刻</strong>
                                    <div class="date-time-picker__time-row">
                                        <select data-hour aria-label="時"></select>
                                        <span>時</span>
                                        <select data-minute aria-label="分"></select>
                                        <span>分</span>
                                    </div>
                                    <div class="date-time-picker__shortcuts">
                                        <button type="button" data-time-shortcut="09:00">09:00</button>
                                        <button type="button" data-time-shortcut="12:00">12:00</button>
                                        <button type="button" data-time-shortcut="18:00">18:00</button>
                                    </div>
                                    <p>日付と時刻を調整し、適用で確定します。</p>
                                </aside>
                            </div>
                            <footer class="date-time-picker__foot">
                                <button class="date-time-picker__text-button" type="button" data-clear>クリア</button>
                                <button class="date-time-picker__text-button" type="button" data-now>現在日時</button>
                                <span></span>
                                <button class="date-time-picker__text-button" type="button" data-cancel>キャンセル</button>
                                <button class="date-time-picker__apply" type="button" data-apply>適用</button>
                            </footer>
                        </section>
                    </div>
                </label>
                <label class="checkbox next-action-alert-toggle">
                    <input type="hidden" name="next_action_alert_enabled" value="0">
                    <input type="checkbox" name="next_action_alert_enabled" value="1" @checked($customer->next_action_alert_enabled)>
                    <span>アラート</span>
                </label>
            </div>
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
    <form method="post" action="{{ route($activityStoreRoute, $customer) }}" class="activity-form">
        @csrf
        @if (request()->boolean('modal'))
            <input type="hidden" name="modal" value="1">
        @endif
        <label>
            日時
            <div class="date-time-picker" data-date-time-picker>
                <input type="hidden" name="action_at" value="{{ now()->format('Y-m-d\TH:i') }}" data-current-datetime data-date-time-value required>
                <button
                    class="date-time-picker__trigger"
                    type="button"
                    aria-haspopup="dialog"
                    aria-expanded="false"
                    aria-controls="activity-action-at-calendar-{{ $customer->id }}"
                >
                    <span data-date-time-label></span>
                    <img class="date-time-picker__icon" src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true">
                </button>
                <section
                    id="activity-action-at-calendar-{{ $customer->id }}"
                    class="date-time-picker__panel"
                    role="dialog"
                    aria-label="架電・対応日時を選択"
                    hidden
                >
                    <div class="date-time-picker__grid">
                        <div>
                            <header class="date-time-picker__head">
                                <button class="date-time-picker__nav" type="button" data-prev-month aria-label="前月">‹</button>
                                <h2 class="date-time-picker__month" data-month-label></h2>
                                <button class="date-time-picker__nav" type="button" data-next-month aria-label="翌月">›</button>
                            </header>
                            <div class="date-time-picker__weekdays" aria-hidden="true">
                                <span>日</span>
                                <span>月</span>
                                <span>火</span>
                                <span>水</span>
                                <span>木</span>
                                <span>金</span>
                                <span>土</span>
                            </div>
                            <div class="date-time-picker__dates" data-dates role="grid" aria-label="日付"></div>
                        </div>
                        <aside class="date-time-picker__time">
                            <strong>時刻</strong>
                            <div class="date-time-picker__time-row">
                                <select data-hour aria-label="時"></select>
                                <span>時</span>
                                <select data-minute aria-label="分"></select>
                                <span>分</span>
                            </div>
                            <div class="date-time-picker__shortcuts">
                                <button type="button" data-time-shortcut="09:00">09:00</button>
                                <button type="button" data-time-shortcut="12:00">12:00</button>
                                <button type="button" data-time-shortcut="18:00">18:00</button>
                            </div>
                            <p>日付と時刻を調整し、適用で確定します。</p>
                        </aside>
                    </div>
                    <footer class="date-time-picker__foot">
                        <button class="date-time-picker__text-button" type="button" data-clear>クリア</button>
                        <button class="date-time-picker__text-button" type="button" data-now>現在日時</button>
                        <span></span>
                        <button class="date-time-picker__text-button" type="button" data-cancel>キャンセル</button>
                        <button class="date-time-picker__apply" type="button" data-apply>適用</button>
                    </footer>
                </section>
            </div>
        </label>
        <label>
            名前
            @if ($isUserScreen)
                <input type="hidden" name="user_id" value="{{ auth()->id() }}">
                <input type="text" value="{{ auth()->user()->name }}" disabled>
            @else
                <select name="user_id" required>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($customer->owner_id === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            @endif
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
        @forelse ($customer->activities as $activity)
            <article class="activity-item is-collapsed">
                <button
                    class="activity-summary-toggle"
                    type="button"
                    data-activity-toggle
                    aria-expanded="false"
                    aria-controls="activity-update-{{ $activity->id }}"
                    aria-label="履歴を展開する"
                >
                    <span class="activity-toggle-icon" aria-hidden="true">▶</span>
                    <span class="activity-summary-toggle__content">
                        <span class="activity-summary-toggle__main">
                            <span class="activity-summary-value">
                                <span>日時</span>
                                <strong>{{ $activity->action_at->format('Y/m/d H:i') }}</strong>
                            </span>
                            <span class="activity-summary-value">
                                <span>担当ステータス</span>
                                <strong>{{ $activity->contact_status ?: '-' }}</strong>
                            </span>
                            <span class="activity-summary-value">
                                <span>ステータス</span>
                                <strong class="status-pill status-pill--{{ \App\Models\Customer::statusClass($activity->status) }}">{{ $activity->status }}</strong>
                            </span>
                        </span>
                        <span class="activity-summary-value activity-summary-value--memo">
                            <span>メモ</span>
                            <strong>{{ $activity->memo ?: '-' }}</strong>
                        </span>
                    </span>
                </button>
                <form id="activity-update-{{ $activity->id }}" method="post" action="{{ route($activityUpdateRoute, [$customer, $activity]) }}">
                    @csrf
                    @method('patch')
                    @if (request()->boolean('modal'))
                        <input type="hidden" name="modal" value="1">
                    @endif
                    <input type="hidden" name="action_at" value="{{ $activity->action_at->format('Y-m-d\TH:i') }}">
                    <input type="hidden" name="user_id" value="{{ $isUserScreen ? auth()->id() : $activity->user_id }}">
                    <div class="activity-item__summary">
                        <div class="activity-summary-field activity-summary-field--readonly">
                            <span>日時</span>
                            <strong>{{ $activity->action_at->format('Y/m/d H:i') }}</strong>
                        </div>
                        <div class="activity-summary-field activity-summary-field--readonly">
                            <span>名前</span>
                            <strong>{{ $activity->user->name }}</strong>
                        </div>
                        <label class="activity-summary-field">
                            <span>担当者</span>
                            <input type="text" name="contact_person" value="{{ $activity->contact_person }}">
                        </label>
                        <label class="activity-summary-field">
                            <span>担当ステータス</span>
                            <select name="contact_status">
                                <option value="">未選択</option>
                                @foreach ($contactStatuses as $contactStatus)
                                    <option value="{{ $contactStatus }}" @selected($activity->contact_status === $contactStatus)>{{ $contactStatus }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="activity-summary-field">
                            <span>ステータス</span>
                            <select name="status" required>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected($activity->status === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="activity-edit">
                        <label class="wide">
                            メモ
                            <textarea name="memo" rows="3" required>{{ $activity->memo }}</textarea>
                        </label>
                    </div>
                </form>
                <div class="activity-item__actions">
                    <button class="button primary" type="submit" form="activity-update-{{ $activity->id }}">履歴を更新</button>
                    @unless ($isUserScreen)
                        <form
                            method="post"
                            action="{{ route('customers.activities.destroy', [$customer, $activity]) }}"
                            data-confirm-submit="この履歴を削除しますか？"
                        >
                            @csrf
                            @method('delete')
                            @if (request()->boolean('modal'))
                                <input type="hidden" name="modal" value="1">
                            @endif
                            <button class="icon-button danger-icon-button" type="submit" aria-label="履歴を削除" title="履歴を削除">
                                <img src="{{ asset('images/trash.png') }}" alt="">
                            </button>
                        </form>
                    @endunless
                </div>
            </article>
        @empty
            <p class="empty-state">まだ架電・対応履歴はありません。</p>
        @endforelse
    </div>
</section>
