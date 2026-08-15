<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('user_management.index', [
            'users' => User::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'regex:/^[^@\s]+@illuvia-inc\.com$/', 'unique:users,email'],
            'initial_password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['appointment', 'sales', 'admin'])],
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['email'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['initial_password']),
                    'role' => $validated['role'],
                    'is_active' => true,
                ]);

                Mail::to($user->email)->send(new UserInvitationMail(
                    $user,
                    $validated['initial_password'],
                    $this->loginUrlForRole($user->role),
                ));
            });
        } catch (Throwable) {
            return redirect()
                ->route('admin.user-management.index')
                ->withInput($request->except('initial_password'))
                ->with('open_user_invite', true)
                ->with('error', '招待メールの送信に失敗しました。メール設定を確認してから再度お試しください。');
        }

        return redirect()
            ->route('admin.user-management.index')
            ->with('status', 'ユーザーを追加し、招待メールを送信しました。');
    }

    private function loginUrlForRole(string $role): string
    {
        return url($role === 'admin' ? '/opnavi/admin/login' : '/opnavi/user/login');
    }
}
