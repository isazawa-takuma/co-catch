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
        $isUserScreen = request()->routeIs('user.*');
        $customerIndexRoute = $isUserScreen ? 'user.customers.index' : 'customers.index';
        $homeRoute = $isUserScreen ? 'user.home' : 'home';
    @endphp
    <div class="app-shell {{ request()->is('opnavi*') ? 'has-sidebar' : '' }}" data-shell>
        @if (request()->is('opnavi*'))
            <aside class="sidebar" data-sidebar>
                <div class="sidebar__head">
                    <a class="brand" href="{{ route($homeRoute) }}">コキャッチ</a>
                    <button class="icon-button" type="button" data-sidebar-toggle title="サイドバーを開閉">≡</button>
                </div>
                <nav class="sidebar__nav">
                    <a href="{{ route($customerIndexRoute) }}" class="{{ request()->routeIs('customers.*') || request()->routeIs('user.customers.*') ? 'active' : '' }}">一覧画面</a>
                    @unless ($isUserScreen)
                        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">営業ダッシュボード</a>
                    @endunless
                </nav>
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
