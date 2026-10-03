<?php
namespace App\Http\Middleware;
use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class ActiveStaffAccount {
    public function handle(Request $request,Closure $next) {
        if ($user=$request->user()) {
            $current=User::find($user->id);
            if (!$current || !$current->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate(); $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors(['email'=>'This account is no longer available.']);
            }
            abort_unless(in_array($current->role,[UserRole::Admin,UserRole::Operator],true),403);
            Auth::guard('web')->setUser($current);
            $request->setUserResolver(fn()=>$current);
        }
        return $next($request);
    }
}
