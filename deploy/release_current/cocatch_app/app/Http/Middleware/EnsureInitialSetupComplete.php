<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureInitialSetupComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ($user->must_change_password || blank($user->last_name) || blank($user->first_name))) {
            return redirect()->route('initial-setup.edit');
        }

        return $next($request);
    }
}
