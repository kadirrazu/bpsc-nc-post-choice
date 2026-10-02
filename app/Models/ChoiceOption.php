<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChoiceOption extends Model { protected $guarded = ['id']; public function post() { return $this->belongsTo(EventPost::class,'event_post_id'); } }
