<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('user_management.index', [
            'users' => User::orderBy('id')->get(),
        ]);
    }
}
