<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class LoginController extends Controller
{
    public function admin()
    {
        if (Auth::check()) {
            return redirect()->route($this->routeForAuthenticatedUser(Auth::user()));
        }

        return view('auth.login', [
            'screen' => 'admin',
            'title' => '管理者ログイン',
            'heading' => '管理者ログイン',
            'submitLabel' => 'ログイン',
            'loginRoute' => 'admin.login.submit',
        ]);
    }

    public function user()
    {
        if (Auth::check()) {
            return redirect()->route($this->routeForAuthenticatedUser(Auth::user()));
        }

        return view('auth.login', [
            'screen' => 'user',
            'title' => 'ユーザーログイン',
            'heading' => 'ユーザーログイン',
            'submitLabel' => 'ログイン',
            'loginRoute' => 'user.login.submit',
        ]);
    }

    public function authenticateAdmin(Request $request)
    {
        return $this->authenticate($request, ['admin'], 'customers.index');
    }

    public function authenticateUser(Request $request)
    {
        return $this->authenticate($request, ['appointment', 'sales'], 'user.customers.index');
    }

    private function authenticate(Request $request, array $allowedRoles, string $redirectRoute)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。']);
        }

        $request->session()->regenerate();

        if (! in_array(Auth::user()->role, $allowedRoles, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'このログイン画面を利用できない権限です。']);
        }

        if ($this->isInitialPasswordExpired(Auth::user())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => '初期パスワードの有効期限が切れています。管理者に再発行を依頼してください。']);
        }

        if ($this->requiresInitialSetup(Auth::user())) {
            return redirect()->route('initial-setup.edit');
        }

        return redirect()->route($redirectRoute);
    }

    public function editInitialSetup()
    {
        return view('auth.initial_setup');
    }

    public function updateInitialSetup(Request $request)
    {
        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'name' => $validated['last_name'].' '.$validated['first_name'],
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'initial_password_expires_at' => null,
        ])->save();

        return redirect()->route($this->homeRouteForRole($user->role));
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route($this->homeRouteForRole($user->role))
            ->with('status', 'パスワードを変更しました。');
    }

    public function logout(Request $request)
    {
        $loginRoute = Auth::user()?->role === 'admin' ? 'admin.login' : 'user.login';

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute);
    }

    private function requiresInitialSetup($user): bool
    {
        return $user->must_change_password || blank($user->last_name) || blank($user->first_name);
    }

    private function isInitialPasswordExpired($user): bool
    {
        return $user->must_change_password
            && $user->initial_password_expires_at
            && $user->initial_password_expires_at->isPast();
    }

    private function homeRouteForRole(string $role): string
    {
        return $role === 'admin' ? 'customers.index' : 'user.customers.index';
    }

    private function routeForAuthenticatedUser($user): string
    {
        if ($this->requiresInitialSetup($user)) {
            return 'initial-setup.edit';
        }

        return $this->homeRouteForRole($user->role);
    }
}
