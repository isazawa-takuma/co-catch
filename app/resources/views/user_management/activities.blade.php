<x-layouts.app title="ユーザー架電状況">
    <div class="page-header">
        <div>
            <p class="eyebrow">ユーザー管理</p>
            <h1>架電状況</h1>
        </div>
        <a class="button" href="{{ route('admin.user-management.index') }}">ユーザー管理へ戻る</a>
    </div>

    <section class="detail-section user-activity-summary">
        <dl>
            <div>
                <dt>名前</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt>メールアドレス</dt>
                <dd>{{ $user->email }}</dd>
            </div>
            <div>
                <dt>権限</dt>
                <dd>{{ $user->role }}</dd>
            </div>
        </dl>
    </section>

    <section class="mypage-summary" aria-labelledby="user-activity-summary-title">
        <h2 id="user-activity-summary-title" class="mypage-summary__title">本日の成績</h2>

        <div class="metric-grid mypage-metrics">
            <div class="metric mypage-today-metric">
                <span>本日の架電数</span>
                <strong>{{ number_format($todayActivityCount) }}</strong>
            </div>
            @foreach ($todayStatusCounts as $statusCount)
                <div class="metric mypage-status-metric">
                    <span>{{ $statusCount['status'] }}</span>
                    <strong>{{ number_format($statusCount['count']) }}</strong>
                </div>
            @endforeach
        </div>

        <div class="mypage-summary-actions">
            <button class="button" type="button" data-mypage-refresh>更新</button>
        </div>
    </section>

    <section class="mypage-history" aria-labelledby="user-activity-history-title">
        <div class="mypage-history__header">
            <h2 id="user-activity-history-title">日付ごとの成績</h2>
            <form class="mypage-history-filter" method="get" action="{{ route('admin.user-management.activities', $user) }}">
                <div class="mypage-history-period" aria-labelledby="user-activity-period-label">
                    <span id="user-activity-period-label">期間</span>
                    <div class="mypage-history-period__controls">
                        <div class="list-date-picker" data-list-date-picker>
                            <input type="hidden" name="result_from" value="{{ $dateRange['result_from'] }}" data-list-date-value>
                            <button
                                class="list-date-picker__trigger"
                                type="button"
                                aria-haspopup="dialog"
                                aria-expanded="false"
                                aria-controls="user-activity-result-from-calendar"
                            >
                                <span data-list-date-label></span>
                                <img class="list-date-picker__icon" src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true">
                            </button>
                            <section
                                id="user-activity-result-from-calendar"
                                class="list-date-picker__calendar"
                                role="dialog"
                                aria-label="開始日を選択"
                                hidden
                            >
                                <header class="list-date-picker__head">
                                    <button class="list-date-picker__nav" type="button" data-prev-month aria-label="前月">‹</button>
                                    <h2 class="list-date-picker__month" data-month-label></h2>
                                    <button class="list-date-picker__nav" type="button" data-next-month aria-label="翌月">›</button>
                                </header>
                                <div class="list-date-picker__weekdays" aria-hidden="true">
                                    <span>日</span><span>月</span><span>火</span><span>水</span><span>木</span><span>金</span><span>土</span>
                                </div>
                                <div class="list-date-picker__dates" data-dates role="grid" aria-label="日付"></div>
                                <footer class="list-date-picker__foot">
                                    <button class="list-date-picker__text-button" type="button" data-clear>クリア</button>
                                    <button class="list-date-picker__text-button" type="button" data-today>今日</button>
                                </footer>
                            </section>
                        </div>

                        <span class="mypage-history-period__separator" aria-hidden="true">〜</span>

                        <div class="list-date-picker" data-list-date-picker>
                            <input type="hidden" name="result_to" value="{{ $dateRange['result_to'] }}" data-list-date-value>
                            <button
                                class="list-date-picker__trigger"
                                type="button"
                                aria-haspopup="dialog"
                                aria-expanded="false"
                                aria-controls="user-activity-result-to-calendar"
                            >
                                <span data-list-date-label></span>
                                <img class="list-date-picker__icon" src="{{ asset('images/calendar.png') }}" alt="" aria-hidden="true">
                            </button>
                            <section
                                id="user-activity-result-to-calendar"
                                class="list-date-picker__calendar"
                                role="dialog"
                                aria-label="終了日を選択"
                                hidden
                            >
                                <header class="list-date-picker__head">
                                    <button class="list-date-picker__nav" type="button" data-prev-month aria-label="前月">‹</button>
                                    <h2 class="list-date-picker__month" data-month-label></h2>
                                    <button class="list-date-picker__nav" type="button" data-next-month aria-label="翌月">›</button>
                                </header>
                                <div class="list-date-picker__weekdays" aria-hidden="true">
                                    <span>日</span><span>月</span><span>火</span><span>水</span><span>木</span><span>金</span><span>土</span>
                                </div>
                                <div class="list-date-picker__dates" data-dates role="grid" aria-label="日付"></div>
                                <footer class="list-date-picker__foot">
                                    <button class="list-date-picker__text-button" type="button" data-clear>クリア</button>
                                    <button class="list-date-picker__text-button" type="button" data-today>今日</button>
                                </footer>
                            </section>
                        </div>
                    </div>
                </div>
                <button class="button primary" type="submit">表示</button>
            </form>
        </div>

        @if ($dailyResults->isEmpty())
            <div class="mypage-history__empty">指定した期間に確定済みの成績はありません。</div>
        @else
            <div class="table-scroll">
                <table class="customer-table mypage-history-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>架電数</th>
                            <th>ステータス別件数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyResults as $result)
                            <tr>
                                <td>{{ $result['date']->format('Y/m/d') }}</td>
                                <td>{{ number_format($result['totalCount']) }}</td>
                                <td>
                                    <div class="mypage-history-statuses">
                                        @forelse ($result['statusCounts'] as $statusCount)
                                            <span class="mypage-history-status">
                                                <span>{{ $statusCount['status'] }}</span>
                                                <strong>{{ number_format($statusCount['count']) }}</strong>
                                            </span>
                                        @empty
                                            <span class="muted-text">内訳なし</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
