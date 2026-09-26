<x-layouts.app title="権限変更">
    <div class="page-header">
        <div>
            <p class="eyebrow">ユーザー管理</p>
            <h1>権限変更</h1>
        </div>
    </div>

    <section class="auth-panel">
        <form class="stack-form" method="post" action="{{ route('admin.user-management.role', $user) }}">
            @csrf
            @method('patch')
            <label>
                対象ユーザー
                <input type="text" value="{{ $user->email }}" disabled>
            </label>
            <label>
                権限
                <select name="role" required>
                    <option value="appointment" @selected(old('role', $user->role) === 'appointment')>アポイント</option>
                    <option value="sales" @selected(old('role', $user->role) === 'sales')>営業</option>
                    <option value="admin" @selected(old('role', $user->role) === 'admin')>管理者</option>
                </select>
            </label>
            <div class="form-actions">
                <a class="button" href="{{ route('admin.user-management.index') }}">戻る</a>
                <button class="button primary" type="submit">適用</button>
            </div>
        </form>
    </section>
</x-layouts.app>
