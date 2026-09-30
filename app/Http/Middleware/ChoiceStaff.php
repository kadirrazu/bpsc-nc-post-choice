<?php
namespace App\Http\Middleware;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
class ChoiceStaff {
    public function handle(Request $request, Closure $next) {
        abort_unless($request->user()?->is_active && in_array($request->user()->role, [UserRole::Admin, UserRole::Operator], true), 403);
        return $next($request);
    }
}
