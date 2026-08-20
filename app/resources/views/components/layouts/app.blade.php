<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'コキャッチ' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</head>
<body>
    @php
        $isUserScreen = request()->routeIs('user.*') || in_array(auth()->user()?->role, ['appointment', 'sales'], true);
        $showsSidebar = request()->is('opnavi*') && ! request()->routeIs('admin.login', 'user.login', 'initial-setup.*');
        $customerIndexRoute = $isUserScreen ? 'user.customers.index' : 'customers.index';
    @endphp
    <div
        class="app-shell {{ $showsSidebar ? 'has-sidebar' : '' }}"
        data-shell
        @if ($showsSidebar && auth()->check())
            data-customer-alerts-url="{{ route('customer-alerts.index') }}"
        @endif
    >
        @if ($showsSidebar)
            <aside class="sidebar" data-sidebar>
                <div class="sidebar__head">
                    @if ($isUserScreen)
                        <span class="brand">コキャッチ</span>
                    @else
                        <a class="brand" href="{{ route('home') }}">コキャッチ</a>
                    @endif
                    <button class="icon-button" type="button" data-sidebar-toggle title="サイドバーを開閉">≡</button>
                </div>
                <nav class="sidebar__nav">
                    <a href="{{ route($customerIndexRoute) }}" class="{{ request()->routeIs('customers.*') || request()->routeIs('user.customers.*') ? 'active' : '' }}">一覧画面</a>
                    @unless ($isUserScreen)
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">営業ダッシュボード</a>
                        <a href="{{ route('admin.user-management.index') }}" class="{{ request()->routeIs('admin.user-management.*') ? 'active' : '' }}">ユーザー管理</a>
                    @endunless
                </nav>
                @auth
                    <div class="sidebar-user">
                        <details class="sidebar-user__menu">
                            <summary>{{ auth()->user()->name }}</summary>
                            <div class="sidebar-user__actions">
                                <a href="{{ route('password.edit') }}">パスワード変更</a>
                                <form method="post" action="{{ route('logout') }}" data-confirm-submit="ログアウトしますか？">
                                    @csrf
                                    <button type="submit">ログアウト</button>
                                </form>
                            </div>
                        </details>
                    </div>
                @endauth
            </aside>
        @endif

        <main class="main">
            @if (session('status') && ! request()->routeIs('customers.index') && ! request()->routeIs('user.customers.index') && session('status_area') !== 'activity')
                <div class="toast success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="toast error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="toast error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

</body>
</html>
