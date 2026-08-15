<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class LoginController extends Controller
{
    public function admin()
    {
        return view('auth.login', [
            'screen' => 'admin',
            'title' => '管理者ログイン',
            'heading' => '管理者ログイン',
            'submitLabel' => 'ログイン',
        ]);
    }

    public function user()
    {
        return view('auth.login', [
            'screen' => 'user',
            'title' => 'ユーザーログイン',
            'heading' => 'ユーザーログイン',
            'submitLabel' => 'ログイン',
        ]);
    }
}
