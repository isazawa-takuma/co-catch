<x-layouts.app title="ユーザー管理">
    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ</p>
            <h1>ユーザー管理</h1>
        </div>
        <button class="button primary" type="button" data-user-invite-open>追加</button>
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

    <div class="modal" data-user-invite-modal hidden>
        <div class="modal__backdrop" data-user-invite-close></div>
        <section class="modal__panel">
            <button class="icon-button modal__close" type="button" data-user-invite-close>×</button>
            <h2>ユーザーを追加</h2>
            <form class="stack-form" data-user-invite-form>
                <label>
                    メールアドレス
                    <input type="email" name="email" placeholder="example@illuvia-inc.com" pattern="^[^@\s]+@illuvia-inc\.com$" autocomplete="email">
                </label>
                <label>
                    初期パスワード
                    <input type="text" name="initial_password" data-user-invite-password autocomplete="new-password">
                </label>
                <label>
                    権限
                    <select name="role">
                        <option value="appointment">アポイント</option>
                        <option value="sales">営業</option>
                        <option value="admin">管理者</option>
                    </select>
                </label>
                <div class="form-actions">
                    <button class="button" type="button" data-user-invite-close>戻る</button>
                    <button class="button primary" type="button" disabled>送信</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
