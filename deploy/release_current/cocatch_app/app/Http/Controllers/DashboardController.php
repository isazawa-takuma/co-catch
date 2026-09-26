<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $metrics = [
            'total' => Customer::count(),
            'not_started' => Customer::where('status', '未対応')->count(),
            'today_actions' => Customer::whereDate('next_action_at', $today)->count(),
            'overdue' => Customer::whereNotNull('next_action_at')->whereDate('next_action_at', '<', $today)->count(),
            'unassigned' => Customer::whereNull('owner_id')->count(),
            'contracted' => Customer::where('status', '契約')->count(),
            'lost' => Customer::where('status', '失注')->count(),
        ];

        return view('dashboard', compact('metrics'));
    }
}
