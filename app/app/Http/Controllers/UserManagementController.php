<?php

namespace App\Http\Controllers;

use App\Mail\UserInvitationMail;
use App\Models\Activity;
use App\Models\DailyCallResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
                    'must_change_password' => true,
                    'initial_password_expires_at' => now()->addWeek(),
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

    public function reissue(User $user)
    {
        $initialPassword = Str::random(12);

        try {
            DB::transaction(function () use ($user, $initialPassword) {
                $user->forceFill([
                    'password' => Hash::make($initialPassword),
                    'must_change_password' => true,
                    'initial_password_expires_at' => now()->addWeek(),
                ])->save();

                Mail::to($user->email)->send(new UserInvitationMail(
                    $user,
                    $initialPassword,
                    $this->loginUrlForRole($user->role),
                ));
            });
        } catch (Throwable) {
            return redirect()
                ->route('admin.user-management.index')
                ->with('error', '初期パスワードの再発行に失敗しました。メール設定を確認してから再度お試しください。');
        }

        return redirect()
            ->route('admin.user-management.index')
            ->with('status', $user->email.' の初期パスワードを再発行しました。');
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
