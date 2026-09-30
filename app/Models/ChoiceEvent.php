<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ChoiceEvent extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['start_at'=>'datetime','end_at'=>'datetime','multiple_posts'=>'boolean']; }
    public function posts() { return $this->hasMany(EventPost::class); }
    public function choices() { return $this->hasMany(ChoiceOption::class)->orderBy('sort_order')->orderBy('id'); }
    public function candidates() { return $this->hasMany(EventCandidate::class); }
    public function scopeAvailable($query) { return $query->where('status','OPEN')->where('start_at','<=',now())->where('end_at','>=',now()); }
    public function getLifecycleAttribute(): string { return $this->end_at->lt(now()) ? 'ARCHIVED' : ($this->status === 'OPEN' && $this->start_at->gt(now()) ? 'SCHEDULED' : $this->status); }
}
