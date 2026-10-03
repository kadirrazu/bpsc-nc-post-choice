<?php
namespace App\Http\Controllers;
use App\Models\ChoiceEvent;
use Illuminate\Http\Request;
class SubmissionStatusController extends Controller {
    public function index(Request $request) {
        $data=$request->validate(['q'=>'nullable|string|max:255']);
        $events=ChoiceEvent::query()->withCount('candidates')->when($data['q']??null,fn($q,$text)=>$q->where(fn($q)=>$q->where('title','like','%'.$text.'%')->orWhere('post_code','like','%'.$text.'%')))->latest('id')->paginate(25)->withQueryString();
        return view('users.submission-status',compact('events'));
    }
}
