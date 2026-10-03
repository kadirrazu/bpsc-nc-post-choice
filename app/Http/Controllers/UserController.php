<?php
namespace App\Http\Controllers;
use App\Enums\UserRole;
use App\Http\Requests\{StoreUserRequest,UpdateUserRequest};
use App\Models\{Designation,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\{Rule,ValidationException};
class UserController extends Controller {
    public function index(Request $request) {
        $data=$request->validate(['search'=>'nullable|string|max:255','role'=>['nullable',Rule::in(['admin','operator'])],'status'=>['nullable',Rule::in(['active','inactive'])]]);
        $search=trim($data['search']??'');
        $users=User::with('designation')->when($search!=='',fn($q)=>$q->where(fn($q)=>$q->where('name','like','%'.$search.'%')->orWhere('email','like','%'.$search.'%')->orWhereHas('designation',fn($q)=>$q->where('name','like','%'.$search.'%'))))->when($data['role']??null,fn($q,$role)=>$q->where('role',$role))->when($data['status']??null,fn($q,$status)=>$q->where('is_active',$status==='active'))->latest('id')->paginate(25)->withQueryString();
        return view('users.index',compact('users','search'));
    }
    public function create() { return view('users.create',['designations'=>$this->designations(),'roles'=>UserRole::options()]); }
    public function store(StoreUserRequest $request) {
        User::create($request->safe()->except('password_confirmation'));
        return redirect()->route('users.index')->with('success','User created successfully.');
    }
    public function show(User $user) { return redirect()->route('users.edit',$user); }
    public function edit(User $user) { return view('users.edit',['user'=>$user,'designations'=>$this->designations($user),'roles'=>UserRole::options()]); }
    public function update(UpdateUserRequest $request,User $user) {
        $data=$request->validated(); $data['is_active']=$request->boolean('is_active');
        if (empty($data['password'])) unset($data['password']); else $data['remember_token']=null;
        DB::transaction(function () use ($request,$user,$data) {
            $admins=User::where('role','admin')->where('is_active',true)->orderBy('id')->lockForUpdate()->get();
            $current=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($request->user()->is($current) && (!$data['is_active'] || $data['role']!=='admin')) throw ValidationException::withMessages(['role'=>'You cannot deactivate or remove administrator access from your own account.']);
            if ($current->role===UserRole::Admin && $current->is_active && (!$data['is_active'] || $data['role']!=='admin') && $admins->count()<=1) throw ValidationException::withMessages(['role'=>'At least one active Administrator must remain.']);
            $current->forceFill($data)->save();
        });
        return redirect()->route('users.index')->with('success','User updated successfully.');
    }
    public function confirmDelete(User $user) { return view('users.delete',compact('user')); }
    public function destroy(Request $request,User $user) {
        $request->validate(['confirmation'=>['required',Rule::in(['DELETE'])]]);
        DB::transaction(function () use ($request,$user) {
            $admins=User::where('role','admin')->where('is_active',true)->orderBy('id')->lockForUpdate()->get();
            $current=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($request->user()->is($current)) throw ValidationException::withMessages(['confirmation'=>'You cannot delete your own account.']);
            if ($current->role===UserRole::Admin && $current->is_active && $admins->count()<=1) throw ValidationException::withMessages(['confirmation'=>'At least one active Administrator must remain.']);
            $current->forceFill(['is_active'=>false,'remember_token'=>null])->save(); $current->delete();
            if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id',$current->id)->delete();
        });
        return redirect()->route('users.index')->with('success','User deleted. This account can no longer sign in.');
    }
    private function designations(?User $user=null) {
        return Designation::where(fn($q)=>$q->where('is_active',true)->when($user?->designation_id,fn($q,$id)=>$q->orWhere('id',$id)))->orderBy('sort_order')->orderBy('name')->get();
    }
}
