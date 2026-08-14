<x-layouts.app title="ユーザー管理">
    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ</p>
            <h1>ユーザー管理</h1>
        </div>
        <button class="button primary" type="button">追加</button>
    </div>

    <section class="table-panel">
        @if ($users->count() === 0)
            <div class="empty-state">
                まだユーザーが登録されていません。
            </div>
        @else
            <div class="table-scroll">
                <table class="customer-table">
                    <thead>
                        <tr>
                            <th>名前</th>
                            <th>メールアドレス</th>
                            <th>権限</th>
                            <th>状態</th>
                            <th>登録日</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role }}</td>
                                <td>{{ $user->is_active ? '有効' : '停止中' }}</td>
                                <td>{{ optional($user->created_at)->format('Y/m/d') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
