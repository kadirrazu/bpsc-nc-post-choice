<?php
namespace App\Http\Controllers;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule,Rules\Password};
class StaffProfileController extends Controller {
    public function edit(Request $request) {
        return view('users.profile',['user'=>$request->user(),'designations'=>Designation::where('is_active',true)->orderBy('sort_order')->get()]);
    }
    public function update(Request $request) {
        $user=$request->user();
        $data=$request->validate([
            'name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($user->id)],
            'designation_id'=>['required','integer',Rule::exists('designations','id')->where('is_active',true)],
            'role'=>'prohibited','is_active'=>'prohibited','user_id'=>'prohibited','id'=>'prohibited','deleted_at'=>'prohibited',
        ]);
        if ($data['email']!==$user->email) $user->email_verified_at=null;
        $user->fill($data)->save();
        return redirect()->route('staff-profile.edit')->with('success','Your profile has been updated.');
    }
    public function password() { return view('users.password'); }
    public function updatePassword(Request $request) {
        $data=$request->validate(['current_password'=>'required|string|current_password:web','password'=>['required','confirmed',Password::min(8)],'user_id'=>'prohibited','id'=>'prohibited','role'=>'prohibited']);
        $request->user()->forceFill(['password'=>$data['password'],'remember_token'=>null])->save();
        return redirect()->route('staff-profile.password')->with('success','Your password has been updated.');
    }
}
