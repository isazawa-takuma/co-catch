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

    <div class="metric-grid user-activity-metrics">
        <div class="metric">
            <span>合計架電数</span>
            <strong>{{ number_format($totalActivityCount) }}</strong>
        </div>
        <div class="metric user-activity-today-metric">
            <span>本日の架電数</span>
            <strong>{{ number_format($todayActivityCount) }}</strong>
        </div>
    </div>

    <section class="table-panel user-activity-panel">
        @if ($activities->count() === 0)
            <div class="empty-state">
                まだ架電・対応履歴はありません。
            </div>
        @else
            <div class="table-scroll">
                <table class="customer-table mypage-table">
                    <thead>
                        <tr>
                            <th class="mypage-date-col">登録日時</th>
                            <th class="mypage-business-col">事業者名</th>
                            <th>メモ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr @class(['mypage-today-row' => $activity->created_at?->isToday()])>
                                <td class="mypage-date-col">{{ optional($activity->created_at)->format('Y/m/d H:i') ?? '-' }}</td>
                                <td class="mypage-business-col">
                                    @if ($activity->customer)
                                        <a class="mypage-business-link" href="{{ route('customers.show', $activity->customer) }}" target="_blank" rel="noopener noreferrer">
                                            {{ $activity->customer->business_name }}
                                        </a>
                                    @else
                                        削除済みの事業者
                                    @endif
                                </td>
                                <td class="mypage-memo-col">{{ $activity->memo }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
