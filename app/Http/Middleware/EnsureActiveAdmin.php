<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if (! $user?->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['login' => 'حساب کاربری شما غیرفعال است.']);
        }

        if ($request->hasSession() && Schema::hasTable('admin_session_users')) {
            DB::table('admin_session_users')->updateOrInsert(
                ['session_id' => $request->session()->getId()],
                ['user_id' => $user->id, 'last_seen_at' => now()],
            );
        }

        return $next($request);
    }
}
