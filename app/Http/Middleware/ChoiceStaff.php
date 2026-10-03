<?php
namespace App\Http\Middleware;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
class ChoiceStaff {
    public function handle(Request $request, Closure $next) {
        $user=$request->user();
        abort_unless($user?->is_active && in_array($user->role,[UserRole::Admin,UserRole::Operator],true),403);
        if ($user->role===UserRole::Operator) {
            abort_unless($request->isMethod('GET') && $request->routeIs('choice-submissions.index'),403);
        }
        return $next($request);
    }
}
