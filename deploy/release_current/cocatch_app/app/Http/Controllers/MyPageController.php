<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyCallResult;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyPageController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->user()->id;
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
            ->where('user_id', $userId)
            ->whereDate('result_date', $now->toDateString())
            ->first();

        if ($todayResult) {
            $todayActivityCount = $todayResult->total_count;
            $todayStatusCounts = $this->sortedStatusCounts(collect($todayResult->status_counts ?? []));
        } else {
            $todayActivities = Activity::query()
                ->where('user_id', $userId)
                ->whereBetween('created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
                ->get(['status']);
            $todayActivityCount = $todayActivities->count();
            $todayStatusCounts = $this->statusCountsForActivities($todayActivities);
        }

        $dailyResults = DailyCallResult::query()
            ->where('user_id', $userId)
            ->whereBetween('result_date', [$dateRange['result_from'], $dateRange['result_to']])
            ->orderByDesc('result_date')
            ->get()
            ->map(fn (DailyCallResult $result) => [
                'date' => $result->result_date,
                'totalCount' => $result->total_count,
                'statusCounts' => $this->sortedStatusCounts(collect($result->status_counts ?? [])),
            ]);

        return view('mypage.show', [
            'todayActivityCount' => $todayActivityCount,
            'todayStatusCounts' => $todayStatusCounts,
            'todayResult' => $todayResult,
            'dateRange' => $dateRange,
            'dailyResults' => $dailyResults,
        ]);
    }

    public function confirm(Request $request)
    {
        $userId = $request->user()->id;
        $now = now();
        $resultDate = $now->toDateString();
        $todayActivities = Activity::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->get(['status']);
        $statusCounts = $this->statusCountsForActivities($todayActivities)
            ->pluck('count', 'status')
            ->all();
        $timestamp = $now->toDateTimeString();

        $inserted = DailyCallResult::query()->insertOrIgnore([
            'user_id' => $userId,
            'result_date' => $resultDate,
            'total_count' => $todayActivities->count(),
            'status_counts' => json_encode($statusCounts, JSON_UNESCAPED_UNICODE),
            'confirmed_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($inserted === 0) {
            return redirect()
                ->route('mypage.show')
                ->with('status', '本日の架電成績はすでに確定済みです。');
        }

        return redirect()
            ->route('mypage.show')
            ->with('status', '本日の架電成績を確定しました。');
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
