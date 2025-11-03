<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->role !== 'user') {
            // 403にするか、管理画面トップへ飛ばすかは運用方針で
            return redirect()->route('admin.attendance.index');
        }
        return $next($request);
    }
}