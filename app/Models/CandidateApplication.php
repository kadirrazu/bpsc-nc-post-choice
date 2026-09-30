<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CandidateApplication extends Model {
    protected $guarded = ['id'];
    public function candidate() { return $this->belongsTo(EventCandidate::class, 'event_candidate_id'); }
}
