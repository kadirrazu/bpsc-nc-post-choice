<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EventCandidate extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['b_date'=>'date','is_demo'=>'boolean']; }
    public function applications() { return $this->hasMany(CandidateApplication::class); }
}
