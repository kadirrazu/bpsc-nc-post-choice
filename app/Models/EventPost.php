<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventPost extends Model {
    protected $guarded = ['id'];
    public function event() { return $this->belongsTo(ChoiceEvent::class, 'choice_event_id'); }
    public function choices() { return $this->hasMany(ChoiceOption::class)->orderBy('sort_order')->orderBy('id'); }
    public function applications() { return $this->hasMany(CandidateApplication::class); }
}
