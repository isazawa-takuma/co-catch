<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyCallResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $credentials = $request->session()->pull('user_credentials');

        return response()->view('user_management.index', [
            'users' => User::orderBy('id')->get(),
            'credentials' => $credentials ? Crypt::decrypt($credentials) : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function activities(Request $request, User $user)
    {
        $now = now();
        $dateRange = validator([
            'result_from' => $request->input('result_from') ?: $now->copy()->startOfMonth()->toDateString(),
            'result_to' => $request->input('result_to') ?: $now->copy()->endOfMonth()->toDateString(),
        ], [
            'result_from' => ['required', 'date'],
            'result_to' => ['required', 'date', 'after_or_equal:result_from'],
        ], [
            'result_from.required' => '開始日を選択してください。',
            'result_from.date' => '開始日に正しい日付を指定してください。',
            'result_to.required' => '終了日を選択してください。',
            'result_to.date' => '終了日に正しい日付を指定してください。',
            'result_to.after_or_equal' => '終了日は開始日以降の日付を指定してください。',
        ])->validate();
        $todayResult = DailyCallResult::query()
            ->where('user_id', $user->id)
            ->whereDate('result_date', $now->toDateString())
            ->first();

        if ($todayResult) {
            $todayActivityCount = $todayResult->total_count;
            $todayStatusCounts = $this->sortedStatusCounts(collect($todayResult->status_counts ?? []));
        } else {
            $todayActivities = Activity::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
                ->get(['status']);
            $todayActivityCount = $todayActivities->count();
            $todayStatusCounts = $this->statusCountsForActivities($todayActivities);
        }

        $dailyResults = DailyCallResult::query()
            ->where('user_id', $user->id)
            ->whereBetween('result_date', [$dateRange['result_from'], $dateRange['result_to']])
            ->orderByDesc('result_date')
            ->get()
            ->map(fn (DailyCallResult $result) => [
                'date' => $result->result_date,
                'totalCount' => $result->total_count,
                'statusCounts' => $this->sortedStatusCounts(collect($result->status_counts ?? [])),
            ]);

        return view('user_management.activities', [
            'user' => $user,
            'todayActivityCount' => $todayActivityCount,
            'todayStatusCounts' => $todayStatusCounts,
            'dateRange' => $dateRange,
            'dailyResults' => $dailyResults,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'regex:/^[^@\s]+@illuvia-inc\.com$/', 'unique:users,email'],
            'initial_password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::in(['appointment', 'sales', 'admin'])],
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {
                return User::create([
                    'name' => $validated['email'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['initial_password']),
                    'role' => $validated['role'],
                    'is_active' => true,
                    'must_change_password' => true,
                    'initial_password_expires_at' => now()->addWeek(),
                ]);
            });
        } catch (Throwable) {
            return redirect()
                ->route('admin.user-management.index')
                ->withInput($request->except('initial_password'))
                ->with('open_user_invite', true)
                ->with('error', 'ユーザーの追加に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->credentialsRedirect($user, $validated['initial_password'])
            ->with('status', 'ユーザーを追加しました。ログイン情報を本人に共有してください。');
    }

    public function reissue(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '自分自身の初期パスワードは再発行できません。パスワード変更をご利用ください。');
        }

        do {
            $initialPassword = Str::random(12);
        } while (! preg_match('/[a-zA-Z]/', $initialPassword) || ! preg_match('/[0-9]/', $initialPassword));

        try {
            DB::transaction(function () use ($user, $initialPassword) {
                $user->forceFill([
                    'password' => Hash::make($initialPassword),
                    'must_change_password' => true,
                    'initial_password_expires_at' => now()->addWeek(),
                ])->save();
            });
        } catch (Throwable) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '初期パスワードの再発行に失敗しました。時間をおいて再度お試しください。');
        }

        return $this->credentialsRedirect($user, $initialPassword)
            ->with('status', $user->email.' の初期パスワードを再発行しました。');
    }

    private function credentialsRedirect(User $user, string $initialPassword)
    {
        // Keep plaintext credentials out of session storage and consume them on the next index view.
        return redirect()
            ->route('admin.user-management.index')
            ->with('user_credentials', Crypt::encrypt([
                'email' => $user->email,
                'initial_password' => $initialPassword,
                'login_url' => $this->loginUrlForRole($user->role),
                'expires_at' => $user->initial_password_expires_at->format('Y/m/d H:i'),
            ]));
    }

    public function editRole(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '自分自身の権限は変更できません。');
        }

        return view('user_management.edit_role', [
            'user' => $user,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '自分自身の権限は変更できません。');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(['appointment', 'sales', 'admin'])],
        ]);

        $user->forceFill([
            'role' => $validated['role'],
        ])->save();

        return redirect()
            ->route('admin.user-management.index')
            ->with('status', $user->email.' の権限を変更しました。');
    }

    public function deactivate(Request $request, User $user)
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '自分自身は無効化できません。');
        }

        $user->forceFill([
            'is_active' => false,
        ])->save();

        return redirect()
            ->route('admin.user-management.index')
            ->with('status', $user->email.' を無効化しました。');
    }

    private function loginUrlForRole(string $role): string
    {
        return url($role === 'admin' ? '/opnavi/admin/login' : '/opnavi/user/login');
    }

    private function statusCountsForActivities(Collection $activities): Collection
    {
        return $this->sortedStatusCounts(
            $activities
                ->filter(fn (Activity $activity) => filled($activity->status))
                ->countBy('status')
        );
    }

    private function sortedStatusCounts(Collection $counts): Collection
    {
        $statusOrder = array_flip(Activity::STATUSES);

        return $counts
            ->map(fn (int $count, string $status) => [
                'status' => $status,
                'count' => $count,
            ])
            ->sort(function (array $left, array $right) use ($statusOrder) {
                $countComparison = $right['count'] <=> $left['count'];

                return $countComparison !== 0
                    ? $countComparison
                    : ($statusOrder[$left['status']] ?? PHP_INT_MAX) <=> ($statusOrder[$right['status']] ?? PHP_INT_MAX);
            })
            ->values();
    }
}
