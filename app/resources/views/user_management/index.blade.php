<x-layouts.app title="ユーザー管理">
    <div class="page-header">
        <div>
            <p class="eyebrow">オペナビ</p>
            <h1>ユーザー管理</h1>
        </div>
        <button class="button primary" type="button" data-user-invite-open>追加</button>
    </div>

    @if ($credentials)
        @php
            $shareText = "コキャッチのログイン情報\n"
                ."ログインID（メールアドレス）: ".$credentials['email']."\n"
                ."初期パスワード: ".$credentials['initial_password']."\n"
                ."ログインURL: ".$credentials['login_url']."\n"
                ."初期パスワードの有効期限: ".$credentials['expires_at']."\n\n"
                ."初回ログイン後、氏名と新しいパスワードを設定してください。";
        @endphp
        <section class="user-credentials stack-form" data-user-credentials aria-labelledby="user-credentials-heading">
            <h2 id="user-credentials-heading">ログイン情報を本人に共有してください</h2>
            <p>メールは送信されません。以下をコピーして、Slackなどの個別チャットで本人に送付してください。</p>
            <label for="user-credentials-text">共有用のログイン情報</label>
            <textarea id="user-credentials-text" rows="8" readonly spellcheck="false" data-user-credentials-text>{{ $shareText }}</textarea>
            <p class="form-hint">この情報は今回のみ表示されます。画面を離れる前にコピーしてください。確認できなくなった場合は初期パスワードを再発行できます。</p>
            <div class="form-actions">
                <button class="button primary" type="button" data-user-credentials-copy>ログイン情報をコピー</button>
                <span role="status" aria-live="polite" data-user-credentials-status></span>
            </div>
        </section>
    @endif

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
                            <th class="row-menu-col" aria-label="操作"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <a class="user-management-name-link" href="{{ route('admin.user-management.activities', $user) }}">
                                        {{ $user->name }}
                                    </a>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role }}</td>
                                <td>{{ $user->is_active ? '有効' : '停止中' }}</td>
                                <td>{{ optional($user->created_at)->format('Y/m/d') ?? '-' }}</td>
                                <td class="row-menu-col">
                                    @unless (auth()->user()->is($user))
                                        <details class="row-action-menu">
                                            <summary aria-label="{{ $user->email }} の操作">⋮</summary>
                                            <div class="row-action-menu__items">
                                                <form method="post" action="{{ route('admin.user-management.reissue', $user) }}" data-confirm-submit="{{ $user->email }} の初期パスワードを再発行しますか？">
                                                    @csrf
                                                    <button type="submit">初期パスワード再発行</button>
                                                </form>
                                                <a href="{{ route('admin.user-management.role.edit', $user) }}">権限変更</a>
                                                <form method="post" action="{{ route('admin.user-management.deactivate', $user) }}" data-confirm-submit="{{ $user->email }} を無効化しますか？">
                                                    @csrf
                                                    @method('patch')
                                                    <button type="submit">無効化</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="modal" data-user-invite-modal @if(!session('open_user_invite') && ! $errors->any()) hidden @endif>
        <div class="modal__backdrop" data-user-invite-close></div>
        <section class="modal__panel">
            <button class="icon-button modal__close" type="button" data-user-invite-close>×</button>
            <h2>ユーザーを追加</h2>
            <p class="form-hint">メールは送信されません。登録後に表示されるログイン情報を本人に共有してください。</p>
            <form class="stack-form" method="post" action="{{ route('admin.user-management.store') }}" data-user-invite-form>
                @csrf
                <label>
                    メールアドレス
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="example@illuvia-inc.com" pattern="^[^@\s]+@illuvia-inc\.com$" autocomplete="email" required>
                </label>
                <label>
                    初期パスワード
                    <input type="text" name="initial_password" data-user-invite-password autocomplete="new-password" required minlength="8">
                    <span class="form-hint">最低8文字・英数字混在</span>
                </label>
                <label>
                    権限
                    <select name="role">
                        <option value="appointment" @selected(old('role') === 'appointment')>アポイント</option>
                        <option value="sales" @selected(old('role') === 'sales')>営業</option>
                        <option value="admin" @selected(old('role') === 'admin')>管理者</option>
                    </select>
                </label>
                <div class="form-actions">
                    <button class="button" type="button" data-user-invite-close>戻る</button>
                    <button class="button primary" type="submit">登録</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
